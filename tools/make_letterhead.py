#!/usr/bin/env python3
"""
YBT GLOBAL INVESTMENT LIMITED — branded letterhead PDF generator.

Brand palette taken from the project's own stylesheet (assets/ybt-combined.min.css):
    green  #10B981 / #059669 / #047857
    gold   #D4AF37  (accents)   tints #D1FAE5, #FFF8E1

Usage:
    python tools/make_letterhead.py                        # blank letterhead
    python tools/make_letterhead.py --preview              # with a sample letter
    python tools/make_letterhead.py --out my.pdf --watermark SPECIMEN
"""
from __future__ import annotations

import argparse
import json
import os
import sys
import tempfile
from datetime import date

from fpdf import FPDF
from PIL import Image

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
LOGO_SRC = os.path.join(ROOT, "assets", "ybt-logo-main.png")
FONTS_DIR = os.path.join(os.environ.get("WINDIR", r"C:\Windows"), "Fonts")

# ── Brand palette (source: assets/ybt-combined.min.css) ──────────────────────
GREEN      = (16, 185, 129)    # #10B981  primary
GREEN_DARK = (5, 150, 105)     # #059669  hover / band depth
GREEN_DEEP = (4, 120, 87)      # #047857  dark accent
GOLD       = (212, 175, 55)    # #D4AF37  accent
GOLD_LIGHT = (255, 248, 225)   # #FFF8E1  light accent
GREEN_TINT = (209, 250, 229)   # #D1FAE5  light tint
INK        = (26, 26, 26)      # #1A1A1A  body text
MUTED      = (96, 107, 101)    # secondary text
WHITE      = (255, 255, 255)

# ── Company details ──────────────────────────────────────────────────────────
COMPANY = "YBT GLOBAL INVESTMENT LIMITED"
WORDMARK = "YBT"
TAGLINE = "PROCUREMENT · LOGISTICS · GLOBAL TRADING"
ADDRESS = "ORE-OLUWA PLAZA, OFFA GARAGE, ILORIN, KWARA STATE, NIGERIA"
PHONE = "0703 135 9944"
EMAIL = "yimikabeautytrend@hotmail.com"

# ── Page grid (mm, A4 portrait) ──────────────────────────────────────────────
PAGE_W, PAGE_H = 210.0, 297.0
LM = RM = 18.0
HEADER_H = 42.0            # green header band
GOLD_RULE_H = 2.2          # gold hairline under the band
BODY_TOP = 54.0            # first writing line
BODY_BOTTOM = 260.0        # last writing line
FOOTER_Y = 282.0           # footer band bleeds off the bottom edge
FOOTER_H = PAGE_H - FOOTER_Y

_logo_cache: str | None = None


# ── Assets ───────────────────────────────────────────────────────────────────
def _crisp_logo() -> str:
    """Flatten the transparent logo onto the band green (fpdf2 alpha-safe,
    and keeps the PDF small — the 800px source is already >300 dpi at print
    size, so no upscaling is needed). Must match the zone the logo sits on
    (the right-hand GREEN band)."""
    global _logo_cache
    if _logo_cache is None:
        with Image.open(LOGO_SRC) as im:
            flat = Image.new("RGBA", im.size, GREEN + (255,))
            flat.alpha_composite(im.convert("RGBA"))
        _logo_cache = os.path.join(tempfile.gettempdir(), "ybt-logo-print.png")
        flat.convert("RGB").save(_logo_cache, optimize=True)
    return _logo_cache


def register_fonts(pdf: FPDF) -> str:
    """Use Segoe UI (Windows) for a premium look; fall back to Helvetica."""
    try:
        pdf.add_font("Segoe", "", os.path.join(FONTS_DIR, "segoeui.ttf"))
        pdf.add_font("Segoe", "B", os.path.join(FONTS_DIR, "segoeuib.ttf"))
        pdf.add_font("Segoe", "I", os.path.join(FONTS_DIR, "segoeuii.ttf"))
        pdf.add_font("Segoe", "BI", os.path.join(FONTS_DIR, "segoeuiz.ttf"))
        pdf.set_font("Segoe", size=10)
        return "Segoe"
    except Exception:
        pdf.set_font("helvetica", size=10)
        return "helvetica"


# ── Page furniture ───────────────────────────────────────────────────────────
def draw_header(pdf: FPDF, family: str) -> None:
    content_h = HEADER_H - GOLD_RULE_H

    # Colour bands: deep green panel on the left, brand green on the right.
    pdf.set_fill_color(*GREEN)
    pdf.rect(0, 0, PAGE_W, HEADER_H, style="F")
    pdf.set_fill_color(*GREEN_DARK)
    pdf.rect(0, 0, PAGE_W * 0.62, HEADER_H, style="F")
    pdf.set_fill_color(*GOLD)
    pdf.rect(0, HEADER_H, PAGE_W, GOLD_RULE_H, style="F")

    # Logo, right-aligned and optically centred in the band.
    logo_h = 17.0
    logo_w = logo_h * 1.5
    pdf.image(_crisp_logo(), x=PAGE_W - RM - logo_w, y=(content_h - logo_h) / 2, h=logo_h)

    # Wordmark: "YBT" in white + company name in gold, auto-fitted on one line.
    size = 16.0
    max_w = PAGE_W - LM - RM - logo_w - 10.0
    pdf.set_font(family, "B", size)
    while pdf.get_string_width(f"{WORDMARK} {COMPANY}") > max_w and size > 10.5:
        size -= 0.5
        pdf.set_font(family, "B", size)

    name_h = 8.6
    tag_h = 3.6
    gap = 1.2
    name_y = (content_h - name_h - gap - tag_h) / 2
    tag_y = name_y + name_h + gap

    pdf.set_text_color(*WHITE)
    pdf.set_xy(LM, name_y)
    pdf.cell(pdf.get_string_width(WORDMARK) + 2.4, name_h, WORDMARK)
    pdf.set_text_color(*GOLD)
    pdf.cell(0, name_h, COMPANY.replace(f"{WORDMARK} ", "", 1))

    # Tagline in letterspaced small caps.
    pdf.set_text_color(*GREEN_TINT)
    pdf.set_font(family, "B", 6.8)
    try:
        pdf.set_char_spacing(0.7)
        spaced = True
    except Exception:
        spaced = False
    pdf.set_xy(LM, tag_y)
    pdf.cell(140.0, tag_h, TAGLINE)
    if spaced:
        pdf.set_char_spacing(0)


def draw_footer(pdf: FPDF, family: str) -> None:
    # Page number floats just above the band, in the body whitespace.
    pdf.set_text_color(*MUTED)
    pdf.set_font(family, "B", 6.5)
    pdf.set_xy(PAGE_W - RM - 30.0, FOOTER_Y - 5.0)
    pdf.cell(30.0, 3.4, f"PAGE {pdf.page_no()} OF {{nb}}", align="R")

    # Green band with a gold hairline on top.
    pdf.set_fill_color(*GREEN)
    pdf.rect(0, FOOTER_Y, PAGE_W, FOOTER_H, style="F")
    pdf.set_fill_color(*GOLD)
    pdf.rect(0, FOOTER_Y, PAGE_W, 0.6, style="F")

    label_y = FOOTER_Y + 3.6
    value_y = label_y + 4.0
    cols = [
        ("ADDRESS", ADDRESS, LM, 84.0),
        ("PHONE", PHONE, LM + 90.0, 32.0),
        ("EMAIL", EMAIL, LM + 128.0, 64.0),
    ]
    for label, value, x, w in cols:
        pdf.set_text_color(*GOLD)
        pdf.set_font(family, "B", 5.9)
        pdf.set_xy(x, label_y)
        pdf.cell(w, 3.0, label)
        pdf.set_text_color(*WHITE)
        pdf.set_font(family, "", 7.0)
        pdf.set_xy(x, value_y)
        pdf.cell(w, 3.8, value)


def draw_watermark(pdf: FPDF, text: str, family: str) -> None:
    cx, cy = PAGE_W / 2, (BODY_TOP + BODY_BOTTOM) / 2
    pdf.set_font(family, "B", 84)
    try:
        pdf.set_text_color(*GREEN_DEEP)
        with pdf.local_context(fill_opacity=0.06):
            with pdf.rotation(36, cx, cy):
                pdf.text(cx - pdf.get_string_width(text) / 2, cy + 12, text)
        return
    except Exception:
        pass
    # Fallback without alpha support: very pale tint instead.
    pdf.set_text_color(233, 246, 240)
    with pdf.rotation(36, cx, cy):
        pdf.text(cx - pdf.get_string_width(text) / 2, cy + 12, text)


class Letterhead(FPDF):
    family: str = "helvetica"
    watermark_text: str | None = None

    def header(self) -> None:  # runs on every page
        draw_header(self, self.family)
        if self.watermark_text:
            draw_watermark(self, self.watermark_text, self.family)
        self.set_text_color(*INK)
        self.set_font(self.family, "", 10.2)
        self.set_xy(LM, BODY_TOP)

    def footer(self) -> None:  # runs on every page
        draw_footer(self, self.family)


# ── Optional letter body (used by --preview) ─────────────────────────────────
def write_letter(
    pdf: Letterhead,
    family: str,
    *,
    date_str: str,
    ref: str,
    recipient: list[str],
    salutation: str,
    subject: str,
    paragraphs: list[str],
    closing: str,
    signer_org: str,
    signer_title: str,
    bullet_heading: str | None = None,
    bullets: list[str] | None = None,
    cc: list[str] | None = None,
    signature_image: str | None = None,
    signature_height: float = 14.0,
) -> None:
    w_body = PAGE_W - LM - RM

    # Date / reference row
    pdf.set_font(family, "", 9.5)
    pdf.set_text_color(*MUTED)
    pdf.set_xy(LM, BODY_TOP + 2)
    pdf.cell(90.0, 5.0, f"DATE: {date_str}")
    ref_txt = f"REF: {ref}"
    pdf.set_xy(PAGE_W - RM - pdf.get_string_width(ref_txt), BODY_TOP + 2)
    pdf.cell(pdf.get_string_width(ref_txt), 5.0, ref_txt)

    y = BODY_TOP + 12

    # Recipient block
    pdf.set_font(family, "", 10.5)
    pdf.set_text_color(*INK)
    for line in recipient:
        pdf.set_xy(LM, y)
        pdf.cell(120.0, 4.8, line)
        y += 4.8

    y += 4
    pdf.set_xy(LM, y)
    pdf.cell(80.0, 5.4, salutation)
    y += 7.5

    # Subject line with a gold accent bar
    pdf.set_fill_color(*GOLD)
    pdf.rect(LM, y + 0.6, 1.4, 6.4, style="F")
    pdf.set_font(family, "B", 10.8)
    pdf.set_text_color(*GREEN_DEEP)
    pdf.set_xy(LM + 5.0, y)
    pdf.multi_cell(w_body - 5.0, 6.4, subject.upper())
    y = pdf.get_y() + 9.5

    # Body: paragraphs and (optionally) inline bullet blocks, in spec order.
    # A "paragraphs" entry may be a plain string or a dict with optional keys
    # "heading" and "bullets" (list of strings).
    pdf.set_text_color(*INK)
    for item in list(paragraphs) + ([{"heading": bullet_heading, "bullets": bullets}] if bullets else []):
        if isinstance(item, dict):
            heading = item.get("heading")
            items = item.get("bullets") or []
            if heading:
                pdf.set_font(family, "B", 10.2)
                pdf.set_xy(LM, y)
                pdf.multi_cell(w_body, 5.9, heading)
                y = pdf.get_y() + 1.8
            for blurb in items:
                pdf.set_fill_color(*GREEN)
                pdf.rect(LM + 1.2, y + 2.25, 1.5, 1.5, style="F")
                pdf.set_font(family, "", 10.2)
                pdf.set_xy(LM + 5.5, y)
                pdf.multi_cell(w_body - 5.5, 5.9, blurb)
                y = pdf.get_y() + 1.4
            y += 0.7
        else:
            pdf.set_font(family, "", 10.2)
            pdf.set_xy(LM, y)
            pdf.multi_cell(w_body, 5.9, item, align="J")
            y = pdf.get_y() + 2.6

    # Closing + signature block
    y += 1
    pdf.set_xy(LM, y)
    pdf.cell(80.0, 5.4, closing)
    y += 6.5

    # Handwritten signature (if provided) sits over the sign line so its
    # descender strokes cross it the way a real signature would.
    sig_placed = False
    if signature_image:
        img_path = (
            signature_image
            if os.path.isabs(signature_image)
            else os.path.join(ROOT, signature_image)
        )
        try:
            with Image.open(img_path) as _sig:
                iw, ih = _sig.size
            sig_h = float(signature_height)
            sig_w = sig_h * iw / ih
            if sig_w > 50.0:  # keep the signature inside the sign-line width
                sig_w, sig_h = 50.0, 50.0 * ih / iw
            pdf.image(img_path, x=LM + 3.0, y=y, w=sig_w, h=sig_h)
            y += max(sig_h - 2.5, 4.0)  # line cuts across the bottom strokes
            sig_placed = True
        except Exception as exc:
            print(f"Warning: could not place signature image: {exc}")
    if not sig_placed:
        y += 5.0  # leave room for a wet-ink signature

    pdf.set_draw_color(*GREEN)
    pdf.set_line_width(0.45)
    pdf.line(LM, y, LM + 62.0, y)
    y += 3.0

    pdf.set_font(family, "B", 10.2)
    pdf.set_text_color(*GREEN_DEEP)
    pdf.set_xy(LM, y)
    pdf.cell(110.0, 5.2, signer_org)
    y += 5.0

    pdf.set_font(family, "", 8.8)
    pdf.set_text_color(*MUTED)
    pdf.set_xy(LM, y)
    pdf.cell(110.0, 4.6, signer_title)

    # CC list
    if cc:
        y += 4.5
        pdf.set_font(family, "", 9.0)
        pdf.set_text_color(*MUTED)
        for line in cc:
            pdf.set_xy(LM, y)
            pdf.multi_cell(w_body, 4.6, line)
            y = pdf.get_y() + 1.2


# Letter fields accepted in a JSON spec (via --spec):
#   date_str, ref, recipient (list of lines), salutation, subject, paragraphs
#   (list of strings), bullet_heading, bullets (list of strings), closing,
#   signer_org, signer_title, cc (list of strings)
SPEC_FIELDS = (
    "date_str", "ref", "recipient", "salutation", "subject", "paragraphs",
    "bullet_heading", "bullets", "closing", "signer_org", "signer_title", "cc",
    "signature_image", "signature_height",
)


def load_spec(path: str) -> dict:
    """Load a letter spec from JSON; unknown keys are rejected loudly."""
    with open(path, encoding="utf-8") as fh:
        data = json.load(fh)
    unknown = set(data) - set(SPEC_FIELDS)
    if unknown:
        sys.exit(f"Unknown spec key(s): {', '.join(sorted(unknown))}. Allowed: {', '.join(SPEC_FIELDS)}")
    return data


SAMPLE = dict(
    date_str=date.today().strftime("%d %B %Y").lstrip("0"),
    ref="YBT/GLM/2026/018",
    recipient=[
        "The Branch Manager,",
        "First Bank of Nigeria Plc,",
        "Ilorin, Kwara State.",
    ],
    salutation="Dear Sir/Madam,",
    subject="Letter of introduction — corporate account opening",
    paragraphs=[
        "We write to introduce YBT Global Investment Limited, a company duly "
        "registered in Nigeria with its head office at Ore-Oluwa Plaza, Offa "
        "Garage, Ilorin, Kwara State. The company is engaged in procurement, "
        "logistics and general trading services for individual and corporate "
        "clients within Nigeria and internationally.",
        "We would be grateful if you could extend to the bearer of this letter "
        "every necessary assistance and courtesy. Should you require further "
        "information or verification, please contact us on 0703 135 9944 or by "
        "email at yimikabeautytrend@hotmail.com.",
    ],
    closing="Yours faithfully,",
    signer_org="For: YBT Global Investment Limited",
    signer_title="Managing Director",
)


# ── Entry point ──────────────────────────────────────────────────────────────
def main(argv: list[str] | None = None) -> None:
    ap = argparse.ArgumentParser(description="Generate the YBT Global Investment Limited letterhead PDF.")
    ap.add_argument(
        "--out",
        default=os.path.join(ROOT, "downloads", "YBT-Global-Investment-Limited-Letterhead.pdf"),
        help="output PDF path",
    )
    ap.add_argument("--preview", action="store_true", help="include a sample letter body")
    ap.add_argument("--spec", metavar="FILE.json", help="write a real letter defined by a JSON spec")
    ap.add_argument("--watermark", metavar="TEXT", help="diagonal watermark, e.g. SPECIMEN")
    args = ap.parse_args(argv)

    pdf = Letterhead(orientation="P", unit="mm", format="A4")
    pdf.set_margins(LM, BODY_TOP, RM)
    # Allow body text to run to 271mm; the page-number chip sits below that,
    # above the footer band.
    pdf.set_auto_page_break(True, margin=PAGE_H - 271.0)
    pdf.alias_nb_pages()

    family = register_fonts(pdf)
    Letterhead.family = family
    Letterhead.watermark_text = args.watermark

    pdf.set_title(f"{COMPANY} — Letterhead")
    pdf.set_author(COMPANY)
    pdf.set_creator("YBT Packer Service — letterhead generator")

    pdf.add_page()
    if args.spec:
        write_letter(pdf, family, **load_spec(args.spec))
    elif args.preview:
        write_letter(pdf, family, **SAMPLE)

    os.makedirs(os.path.dirname(os.path.abspath(args.out)), exist_ok=True)
    pdf.output(args.out)
    size_kb = os.path.getsize(args.out) / 1024
    print(f"Written: {args.out} ({size_kb:.0f} KB, font: {family})")


if __name__ == "__main__":
    main()
