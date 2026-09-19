<?php
/**
 * YBT Maintenance: compress images already stored on the server.
 *
 * Finds every image referenced in the database (tracking images, payment
 * evidence, carton images, chat attachments) and re-encodes the local file
 * in place — downscaling anything larger than --max-dim and re-encoding
 * JPEG/WebP at --quality. Files are only replaced when the new version is
 * actually smaller, so nothing ever grows and URLs in the database stay valid.
 *
 * CLI ONLY (needs the GD extension, which the Apache SAPI on this host lacks):
 *
 *   Dry run (shows what would be saved, changes nothing):
 *     C:/xampp/php/php.exe compress-existing-images.php --dry-run
 *
 *   Run for real:
 *     C:/xampp/php/php.exe compress-existing-images.php
 *
 *   Optional flags:
 *     --max-dim=1600        longest allowed edge in px (default 1600)
 *     --quality=82          JPEG/WebP quality (default 82)
 *     --min-size=100000     skip files already smaller than this (default 100 KB)
 *     --remove-missing      also strip DB references whose file no longer exists
 */

if (PHP_SAPI !== 'cli') {
	exit("CLI only. Run with: C:/xampp/php/php.exe compress-existing-images.php\n");
}

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('memory_limit', '512M');
set_time_limit(0);

// ── Flags ────────────────────────────────────────────────────────────────
$dryRun    = in_array('--dry-run', $argv, true);
$maxDim    = 1600;
$quality   = 82;
$minSize   = 100000;
$rmMissing = in_array('--remove-missing', $argv, true);

foreach ($argv as $a) {
	if (preg_match('/^--max-dim=(\d+)$/', $a, $m))  $maxDim  = max(200, (int) $m[1]);
	if (preg_match('/^--quality=(\d+)$/', $a, $m))  $quality = min(95, max(40, (int) $m[1]));
	if (preg_match('/^--min-size=(\d+)$/', $a, $m)) $minSize = (int) $m[1];
}

// ── Bootstrap WordPress ──────────────────────────────────────────────────
$wpLoad = dirname(__DIR__, 3) . '/wp-load.php';
if (!file_exists($wpLoad)) {
	// Fallback: script might sit inside includes/ or be run from elsewhere
	$candidate = getcwd() . '/wp-load.php';
	if (file_exists($candidate)) {
		$wpLoad = $candidate;
	} else {
		exit("Could not locate wp-load.php\n");
	}
}
require $wpLoad;

global $wpdb;
if (!function_exists('imagecreatetruecolor')) {
	exit("GD extension is not available in this PHP binary — cannot compress.\n");
}

$uploads = wp_upload_dir();
$basedir = rtrim($uploads['basedir'], '/\\');
$baseurl = rtrim($uploads['baseurl'], '/');

echo "YBT image compression " . ($dryRun ? "(DRY RUN — nothing will be modified)\n" : "\n");
echo "Uploads dir: $basedir\n";
echo "Settings: max-dim={$maxDim}px  quality={$quality}  min-size={$minSize}\n";
echo str_repeat('-', 96) . "\n";

// ── Collect URL references ───────────────────────────────────────────────
// Returns list of [ table, column, rowId, rawValue ]
$refs = array();

$rows = $wpdb->get_results("SELECT id, images, payment_evidence FROM {$wpdb->prefix}ybt_tracking");
foreach ($rows as $r) {
	if (!empty($r->images))          $refs[] = array($wpdb->prefix . 'ybt_tracking', 'images', (int) $r->id, $r->images);
	if (!empty($r->payment_evidence)) $refs[] = array($wpdb->prefix . 'ybt_tracking', 'payment_evidence', (int) $r->id, $r->payment_evidence);
}

$cartons = $wpdb->get_results("SELECT id, carton_image FROM {$wpdb->prefix}ybt_cartons WHERE carton_image IS NOT NULL AND carton_image != ''");
foreach ($cartons as $c) {
	$refs[] = array($wpdb->prefix . 'ybt_cartons', 'carton_image', (int) $c->id, $c->carton_image);
}

$chats = $wpdb->get_results("SELECT id, attachment FROM {$wpdb->prefix}ybt_chat WHERE attachment IS NOT NULL AND attachment != ''");
foreach ($chats as $c) {
	$refs[] = array($wpdb->prefix . 'ybt_chat', 'attachment', (int) $c->id, $c->attachment);
}

/**
 * Parse a stored value into a list of URLs. Values may be a comma-separated
 * list or a JSON array (the Android app stores both shapes).
 */
function ybt_parse_urls($raw) {
	if ($raw === null || $raw === '') return array();
	$raw = trim($raw);
	if ($raw[0] === '[') {
		$json = json_decode($raw, true);
		if (is_array($json)) {
			return array_values(array_filter(array_map('trim', $json)));
		}
	}
	$parts = explode(',', $raw);
	return array_values(array_filter(array_map('trim', $parts)));
}

/** Map an upload URL to a local file path, or null if it isn't a local upload. */
function ybt_url_to_path($url, $baseurl, $basedir) {
	$url = html_entity_decode($url);
	if (strpos($url, $baseurl) !== 0) return null;
	$rel  = substr($url, strlen($baseurl));
	$path = $basedir . str_replace('/', DIRECTORY_SEPARATOR, $rel);
	return $path;
}

// ── Compression ──────────────────────────────────────────────────────────
/**
 * Compress a local image file with GD.
 * Returns array(new_size, old_size) or null when skipped/no benefit.
 * In dry-run mode the temp file is written then discarded — the original stays.
 */
function ybt_compress_file($path, $maxDim, $quality, $dryRun) {
	if (!file_exists($path) || filesize($path) === 0) return null;
	$info = @getimagesize($path);
	if (!$info || empty($info['mime'])) return null;
	$mime = $info['mime'];

	$old = filesize($path);
	if ($old < 2048) return null; // trivially small, skip

	switch ($mime) {
		case 'image/jpeg':
			$src = @imagecreatefromjpeg($path);
			$save = 'imagejpeg';
			$saveQ = $quality;
			break;
		case 'image/webp':
			if (!function_exists('imagecreatefromwebp')) return null;
			$src = @imagecreatefromwebp($path);
			$save = 'imagewebp';
			$saveQ = $quality;
			break;
		case 'image/png':
			$src = @imagecreatefrompng($path);
			$save = 'imagepng';
			$saveQ = 6; // compression level, not quality
			break;
		default:
			return null; // GIF (may be animated), PDF, anything else: skip
	}
	if (!$src) return null;

	$w = imagesx($src);
	$h = imagesy($src);

	if (max($w, $h) > $maxDim) {
		$scale = min($maxDim / $w, $maxDim / $h);
		$nw = max(1, (int) round($w * $scale));
		$nh = max(1, (int) round($h * $scale));
		$dst = imagecreatetruecolor($nw, $nh);
		if ($mime !== 'image/jpeg') {
			imagealphablending($dst, false);
			imagesavealpha($dst, true);
			$t = imagecolorallocatealpha($dst, 0, 0, 0, 127);
			imagefilledrectangle($dst, 0, 0, $nw, $nh, $t);
			imagealphablending($dst, true);
		}
		imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
	} else {
		$dst = $src;
	}

	$tmp = $path . '.compress_tmp';
	$ok  = false;
	if ($save === 'imagejpeg')      $ok = @imagejpeg($dst, $tmp, $saveQ);
	elseif ($save === 'imagepng')   $ok = @imagepng($dst, $tmp, $saveQ);
	elseif ($save === 'imagewebp')  $ok = @imagewebp($dst, $tmp, $saveQ);

	$result = null;
	if ($ok && file_exists($tmp)) {
		clearstatcache(true, $tmp);
		$new = filesize($tmp);
		if ($new > 0 && $new < $old) {
			if (!$dryRun) {
				// Replace atomically: remove target then rename (rename fails over existing file on Windows)
				if (@unlink($path) && @rename($tmp, $path)) {
					$result = array($new, $old);
				} else {
					@unlink($tmp);
				}
			} else {
				@unlink($tmp);
				$result = array($new, $old);
			}
		} else {
			@unlink($tmp);
		}
	} else {
		@unlink($tmp);
	}

	if ($dst !== $src) imagedestroy($dst);
	imagedestroy($src);
	return $result;
}

// ── Walk references ──────────────────────────────────────────────────────
$totalOld = 0; $totalNew = 0; $compressed = 0; $skipped = 0; $missing = 0;
$deadRefs = array(); // [ table, column, rowId, url ]

$seenFiles = array(); // compress each unique file once

foreach ($refs as $ref) {
	list($table, $column, $rowId, $rawValue) = $ref;
	$urls = ybt_parse_urls($rawValue);
	$changed = false;
	$keptUrls = array();

	foreach ($urls as $url) {
		$path = ybt_url_to_path($url, $baseurl, $basedir);

		if ($path === null || !file_exists($path)) {
			$missing++;
			$deadRefs[] = array($table, $column, $rowId, $url);
			$keptUrls[] = $url; // keep unless --remove-missing
			continue;
		}

		$key = strtolower($path);
		if (!isset($seenFiles[$key])) {
			$seenFiles[$key] = true;
			if (filesize($path) < $minSize) {
				$skipped++;
			} else {
				$res = ybt_compress_file($path, $maxDim, $quality, $dryRun);
				if ($res === null) {
					$skipped++;
				} else {
					list($new, $old) = $res;
					$compressed++;
					$totalOld += $old;
					$totalNew += $new;
					$pct = $old > 0 ? round(100 - ($new / $old * 100)) : 0;
					printf("%-11s %8s -> %-8s (-%d%%)  %s\n",
						$dryRun ? 'WOULD SAVE' : 'SAVED',
						number_format($old), number_format($new), $pct,
						basename($path));
				}
			}
		}
		$keptUrls[] = $url;
	}

	// Rewrite the stored value when --remove-missing dropped dead URLs
	if ($rmMissing && count($keptUrls) !== count($urls)) {
		$newValue = count($keptUrls) ? implode(',', $keptUrls) : '';
		$wpdb->update($table, array($column => $newValue), array('id' => $rowId));
		$changed = true;
	}
}

echo str_repeat('-', 96) . "\n";
printf("Files compressed : %d\n", $compressed);
printf("Files skipped    : %d (already small, non-image, or no gain)\n", $skipped);
printf("Missing files    : %d references point to files that no longer exist\n", $missing);
if ($compressed > 0) {
	printf("Total space      : %s -> %s  (saved %s, %d%%)\n",
		number_format($totalOld), number_format($totalNew),
		number_format($totalOld - $totalNew),
		$totalOld > 0 ? (int) round(100 - ($totalNew / $totalOld * 100)) : 0);
}
if ($missing > 0 && !$rmMissing) {
	echo "\nTip: re-run with --remove-missing to strip the dead references from the database.\n";
}
if ($dryRun) {
	echo "\nDry run complete — no files were modified. Run without --dry-run to apply.\n";
}
