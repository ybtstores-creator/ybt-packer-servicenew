<?php
/**
 * Reusable row renderer for Process Packing item rows.
 *
 * Shared by the Process Packing page (via the lazy-row AJAX handler
 * ybt_get_packing_group_rows in includes/tracking.php) so the markup served
 * on first expand can never drift from the canonical row markup.
 *
 * Expected variables set by the caller:
 *
 * @var array  $items           Tracking rows for one user group
 * @var string $username        Group username
 * @var object $warehouse_cache [shipping_type => settings row]
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<?php foreach ($items as $item): ?>
    <tr data-id="<?php echo esc_attr($item->id); ?>" data-user="<?php echo esc_attr($username); ?>">
        <td style="text-align: center;"><input type="checkbox" class="item-checkbox" value="<?php echo esc_attr($item->id); ?>" data-user="<?php echo esc_attr($username); ?>"></td>
        <td>
            <?php
            $method_key = in_array($item->shipping_method, array('air', 'sea')) ? $item->shipping_method : 'sea';
            $warehouse_info = isset($warehouse_cache[$method_key]) ? $warehouse_cache[$method_key] : null;
            if ($warehouse_info): ?>
                <div style="font-size: 11px; line-height: 1.2;">
                    <strong><?php esc_html_e('Warehouse:', 'ybt-packer'); ?></strong><br><span class="text-muted"><?php echo nl2br(esc_html($warehouse_info->address)); ?></span>
                </div>
            <?php else: ?>
                <span class="text-muted small"><?php esc_html_e('N/A', 'ybt-packer'); ?></span>
            <?php endif; ?>
        </td>
        <td><span class="ybd-trackno"><?php echo esc_html($item->tracking_number); ?></span></td>
        <td><?php echo esc_html($item->description ?: __('No description', 'ybt-packer')); ?></td>
        <td class="small text-muted">
            <?php
            $req_date = $item->date_packing_requested ?: $item->date_added;
            echo esc_html(date('M j', strtotime($req_date)));
            ?>
        </td>
        <td>
            <span class="ybd-batch ybd-batch-sm">
                <?php echo ($item->shipping_method === 'air') ? '✈ Air' : '🚢 Sea'; ?>
            </span>
        </td>
        <td>
            <?php if ($item->images): ?>
                <?php
                $images = array_filter(array_map('trim', explode(',', $item->images)));
                ?>
                <div class="image-thumbnails" style="display: flex; gap: 5px; flex-wrap: wrap;">
                    <?php foreach (array_slice($images, 0, 3) as $image): ?>
                        <img src="<?php echo esc_url($image); ?>"
                             loading="lazy" decoding="async"
                             style="width: 32px; height: 32px; object-fit: cover; border-radius: 4px; cursor: pointer; border: 1px solid #ddd;"
                             onclick="openImageFullscreen('<?php echo esc_js($image); ?>')"
                             title="Click to view">
                    <?php endforeach; ?>
                    <?php if (count($images) > 3): ?>
                        <span style="font-size: 11px; color: #666; align-self: center;">+<?php echo count($images) - 3; ?></span>
                    <?php endif; ?>
                </div>
                <button class="button button-small view-images mt-1" data-images="<?php echo esc_attr($item->images); ?>" style="font-size: 10px; padding: 2px 6px; height: auto;">
                    <?php esc_html_e('View All', 'ybt-packer'); ?>
                </button>
            <?php else: ?>
                <span class="text-muted small">-</span>
            <?php endif; ?>
        </td>
        <td>
            <input type="number" class="item-count-input" data-id="<?php echo esc_attr($item->id); ?>"
                   value="<?php echo esc_attr($item->item_count ?: '1'); ?>"
                   min="1" style="width: 50px; padding: 2px;">
        </td>
        <td>
            <label class="complete-checkbox-label">
                <input type="checkbox" class="complete-checkbox" data-id="<?php echo esc_attr($item->id); ?>"
                       <?php checked($item->is_complete, 1); ?>>
                <span class="checkmark"></span>
            </label>
        </td>
        <td>
            <div class="d-flex gap-1 flex-wrap">
                <button class="button button-small view-tracking-details" data-id="<?php echo esc_attr($item->id); ?>" title="<?php esc_attr_e('View Details', 'ybt-packer'); ?>">
                    👁️
                </button>
                <button class="button button-small button-primary mark-packed" data-id="<?php echo esc_attr($item->id); ?>" title="<?php esc_attr_e('Mark Packed', 'ybt-packer'); ?>">
                    ✓
                </button>
                <button class="button button-small upload-packing-images" data-id="<?php echo esc_attr($item->id); ?>" title="<?php esc_attr_e('Upload Images', 'ybt-packer'); ?>">
                    📷
                </button>
                 <button class="button button-small attach-cartons"
                     data-id="<?php echo esc_attr($item->id); ?>"
                     data-batch="<?php echo esc_attr($item->batch_id); ?>" title="<?php esc_attr_e('Attach Cartons', 'ybt-packer'); ?>">
                     📦
                 </button>
            </div>
        </td>
    </tr>
<?php endforeach; ?>
