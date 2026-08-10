from __future__ import annotations

import html
import re
from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfbase import pdfmetrics
from reportlab.platypus import (
    BaseDocTemplate,
    CondPageBreak,
    Frame,
    KeepTogether,
    PageBreak,
    PageTemplate,
    Paragraph,
    Spacer,
    Table,
    TableStyle,
)
from reportlab.platypus.tableofcontents import TableOfContents


ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "docs" / "MeroMaidan_PRD_v3.md"
OUTPUT = ROOT / "output" / "pdf" / "MeroMaidan_PRD_v3_Final_Business_Model.pdf"

NAVY = colors.HexColor("#0B2239")
NAVY_2 = colors.HexColor("#123B5C")
GREEN = colors.HexColor("#18A957")
ORANGE = colors.HexColor("#F9631C")
INK = colors.HexColor("#263B4D")
MUTED = colors.HexColor("#667B8F")
LINE = colors.HexColor("#DCE5EB")
PALE = colors.HexColor("#F5F8FA")
PALE_GREEN = colors.HexColor("#EAF8F0")
PALE_ORANGE = colors.HexColor("#FFF1E9")


def register_fonts() -> tuple[str, str]:
    candidates = [
        (Path("C:/Windows/Fonts/aptos.ttf"), Path("C:/Windows/Fonts/aptosbd.ttf")),
        (Path("C:/Windows/Fonts/arial.ttf"), Path("C:/Windows/Fonts/arialbd.ttf")),
    ]
    for regular, bold in candidates:
        if regular.exists() and bold.exists():
            pdfmetrics.registerFont(TTFont("MMRegular", str(regular)))
            pdfmetrics.registerFont(TTFont("MMBold", str(bold)))
            return "MMRegular", "MMBold"
    return "Helvetica", "Helvetica-Bold"


REGULAR, BOLD = register_fonts()


class PRDDocTemplate(BaseDocTemplate):
    def __init__(self, filename: str):
        super().__init__(
            filename,
            pagesize=A4,
            leftMargin=19 * mm,
            rightMargin=19 * mm,
            topMargin=20 * mm,
            bottomMargin=18 * mm,
            title="MeroMaidan Product Requirements Document v3.0",
            author="MeroMaidan",
            subject="Final annual subscription, Recommended Venue, and Event Promotion model",
        )
        frame = Frame(self.leftMargin, self.bottomMargin, self.width, self.height, id="normal")
        self.addPageTemplates(PageTemplate(id="main", frames=frame, onPage=self.draw_page))

    def draw_page(self, canvas, doc):
        canvas.saveState()
        if doc.page > 1:
            canvas.setStrokeColor(LINE)
            canvas.line(self.leftMargin, A4[1] - 14 * mm, A4[0] - self.rightMargin, A4[1] - 14 * mm)
            canvas.setFont(BOLD, 8)
            canvas.setFillColor(NAVY)
            canvas.drawString(self.leftMargin, A4[1] - 11 * mm, "MeroMaidan PRD")
            canvas.setFont(REGULAR, 7.5)
            canvas.setFillColor(MUTED)
            canvas.drawRightString(A4[0] - self.rightMargin, A4[1] - 11 * mm, "Version 3.0 | 9 August 2026")
            canvas.setStrokeColor(LINE)
            canvas.line(self.leftMargin, 12 * mm, A4[0] - self.rightMargin, 12 * mm)
            canvas.setFont(REGULAR, 7.5)
            canvas.setFillColor(MUTED)
            canvas.drawString(self.leftMargin, 8 * mm, "Approved business-model baseline")
            canvas.drawRightString(A4[0] - self.rightMargin, 8 * mm, f"Page {doc.page}")
        canvas.restoreState()

    def afterFlowable(self, flowable):
        if isinstance(flowable, Paragraph):
            style_name = flowable.style.name
            if style_name in ("H2", "H3"):
                level = 0 if style_name == "H2" else 1
                text = flowable.getPlainText()
                key = "section-%s" % abs(hash((text, self.page)))
                self.canv.bookmarkPage(key)
                self.canv.addOutlineEntry(text, key, level=level, closed=False)
                self.notify("TOCEntry", (level, text, self.page, key))


styles = getSampleStyleSheet()
styles.add(ParagraphStyle(
    name="CoverKicker", fontName=BOLD, fontSize=9, leading=11, textColor=GREEN,
    alignment=TA_CENTER, spaceAfter=7, uppercase=True,
))
styles.add(ParagraphStyle(
    name="CoverTitle", fontName=BOLD, fontSize=29, leading=34, textColor=colors.white,
    alignment=TA_CENTER, spaceAfter=12,
))
styles.add(ParagraphStyle(
    name="CoverSub", fontName=REGULAR, fontSize=11, leading=17, textColor=colors.HexColor("#D6E4EF"),
    alignment=TA_CENTER,
))
styles.add(ParagraphStyle(
    name="H2", fontName=BOLD, fontSize=18, leading=23, textColor=NAVY,
    spaceBefore=12, spaceAfter=9, keepWithNext=True,
))
styles.add(ParagraphStyle(
    name="H3", fontName=BOLD, fontSize=12.2, leading=16, textColor=NAVY_2,
    spaceBefore=10, spaceAfter=5, keepWithNext=True,
))
styles.add(ParagraphStyle(
    name="BodyMM", fontName=REGULAR, fontSize=9.25, leading=14, textColor=INK,
    spaceAfter=6,
))
styles.add(ParagraphStyle(
    name="BulletMM", fontName=REGULAR, fontSize=9, leading=13.5, textColor=INK,
    leftIndent=13, firstLineIndent=-7, bulletIndent=3, spaceAfter=3,
))
styles.add(ParagraphStyle(
    name="NumberMM", fontName=REGULAR, fontSize=9, leading=13.5, textColor=INK,
    leftIndent=16, firstLineIndent=-10, spaceAfter=3,
))
styles.add(ParagraphStyle(
    name="FlowMM", fontName=BOLD, fontSize=8.4, leading=12, textColor=NAVY,
    borderColor=LINE, borderWidth=0.7, borderPadding=8, backColor=PALE,
    spaceBefore=3, spaceAfter=8,
))
styles.add(ParagraphStyle(
    name="TableHead", fontName=BOLD, fontSize=7.5, leading=9.5, textColor=colors.white,
))
styles.add(ParagraphStyle(
    name="TableCell", fontName=REGULAR, fontSize=7.4, leading=9.7, textColor=INK,
))


def inline_markup(text: str) -> str:
    value = html.escape(text.strip())
    value = re.sub(r"\*\*(.+?)\*\*", r"<b>\1</b>", value)
    value = re.sub(r"`(.+?)`", r"<font name='Courier' color='#123B5C'>\1</font>", value)
    return value


def make_table(rows: list[list[str]], width: float) -> Table:
    cols = max(len(row) for row in rows)
    normalized = [row + [""] * (cols - len(row)) for row in rows]
    data = []
    for row_index, row in enumerate(normalized):
        style = styles["TableHead"] if row_index == 0 else styles["TableCell"]
        data.append([Paragraph(inline_markup(cell), style) for cell in row])
    if cols == 4:
        col_widths = [width * 0.23, width * 0.15, width * 0.17, width * 0.45]
    elif cols == 5:
        col_widths = [width * 0.16, width * 0.22, width * 0.21, width * 0.22, width * 0.19]
    else:
        col_widths = [width / cols] * cols
    table = Table(data, colWidths=col_widths, repeatRows=1, hAlign="LEFT")
    table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), NAVY),
        ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("GRID", (0, 0), (-1, -1), 0.45, LINE),
        ("BACKGROUND", (0, 1), (-1, -1), colors.white),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, PALE]),
        ("LEFTPADDING", (0, 0), (-1, -1), 6),
        ("RIGHTPADDING", (0, 0), (-1, -1), 6),
        ("TOPPADDING", (0, 0), (-1, -1), 6),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
    ]))
    return table


def cover_story(doc: PRDDocTemplate) -> list:
    service_data = [
        [Paragraph("ANNUAL VENUE SUBSCRIPTION", styles["TableHead"]), Paragraph("RECOMMENDED VENUE", styles["TableHead"]), Paragraph("EVENT PROMOTION", styles["TableHead"])],
        [Paragraph("<b>NPR 9,999 / year</b><br/>One venue", styles["TableCell"]), Paragraph("<b>NPR 1,000 / month</b><br/>Location visibility", styles["TableCell"]), Paragraph("<b>Configurable / TBC</b><br/>Hero campaign", styles["TableCell"])],
    ]
    card = Table(service_data, colWidths=[doc.width / 3] * 3, repeatRows=1)
    card.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), ORANGE),
        ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
        ("BACKGROUND", (0, 1), (-1, 1), colors.white),
        ("GRID", (0, 0), (-1, -1), 0.6, colors.HexColor("#D7E1E8")),
        ("ALIGN", (0, 0), (-1, -1), "CENTER"),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING", (0, 0), (-1, -1), 11),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 11),
        ("LEFTPADDING", (0, 0), (-1, -1), 8),
        ("RIGHTPADDING", (0, 0), (-1, -1), 8),
    ]))
    panel = Table([[Paragraph("MEROMAIDAN", styles["CoverKicker"])],
                   [Paragraph("Product Requirements<br/>Document", styles["CoverTitle"])],
                   [Paragraph("Final pricing, subscription, Recommended Venue, Event Promotion, coupon, booking, UI/UX, database, and workflow specification", styles["CoverSub"])]],
                  colWidths=[doc.width])
    panel.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), NAVY),
        ("BOX", (0, 0), (-1, -1), 0, NAVY),
        ("TOPPADDING", (0, 0), (-1, 0), 28),
        ("BOTTOMPADDING", (0, 2), (-1, 2), 34),
        ("LEFTPADDING", (0, 0), (-1, -1), 24),
        ("RIGHTPADDING", (0, 0), (-1, -1), 24),
    ]))
    meta = Table([
        ["Version", "3.0"], ["Status", "Approved business-model baseline"],
        ["Date", "9 August 2026"], ["Market", "Nepal"],
    ], colWidths=[35 * mm, doc.width - 35 * mm])
    meta.setStyle(TableStyle([
        ("FONTNAME", (0, 0), (0, -1), BOLD),
        ("FONTNAME", (1, 0), (1, -1), REGULAR),
        ("FONTSIZE", (0, 0), (-1, -1), 8.5),
        ("TEXTCOLOR", (0, 0), (0, -1), NAVY),
        ("TEXTCOLOR", (1, 0), (1, -1), INK),
        ("GRID", (0, 0), (-1, -1), 0.45, LINE),
        ("BACKGROUND", (0, 0), (0, -1), PALE_GREEN),
        ("TOPPADDING", (0, 0), (-1, -1), 7),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
        ("LEFTPADDING", (0, 0), (-1, -1), 8),
    ]))
    return [
        Spacer(1, 14 * mm), panel, Spacer(1, 11 * mm), card, Spacer(1, 13 * mm), meta,
        Spacer(1, 13 * mm), Paragraph("Three independent commercial services. One clear venue subscription. Paid placement never rewrites organic search.", ParagraphStyle(name="CoverNote", parent=styles["BodyMM"], fontName=BOLD, textColor=NAVY, alignment=TA_CENTER, fontSize=10, leading=15)),
        PageBreak(),
    ]


def parse_markdown(text: str, doc: PRDDocTemplate) -> list:
    lines = text.splitlines()
    story = []
    paragraph_lines: list[str] = []
    table_rows: list[list[str]] = []

    def flush_paragraph():
        nonlocal paragraph_lines
        if paragraph_lines:
            raw = " ".join(piece.strip() for piece in paragraph_lines).strip()
            if raw:
                style = styles["FlowMM"] if raw.startswith("`") and raw.endswith("`") else styles["BodyMM"]
                story.append(Paragraph(inline_markup(raw), style))
            paragraph_lines = []

    def flush_table():
        nonlocal table_rows
        if table_rows:
            story.append(make_table(table_rows, doc.width))
            story.append(Spacer(1, 8))
            table_rows = []

    for line in lines:
        stripped = line.strip()
        if stripped.startswith("# ") or stripped.startswith("Version:") or stripped.startswith("Status:") or stripped.startswith("Date:") or stripped.startswith("Product:") or stripped.startswith("Primary market:"):
            continue
        if stripped.startswith("|") and stripped.endswith("|"):
            flush_paragraph()
            cells = [cell.strip() for cell in stripped[1:-1].split("|")]
            if all(re.fullmatch(r":?-{3,}:?", cell) for cell in cells):
                continue
            table_rows.append(cells)
            continue
        flush_table()
        if not stripped:
            flush_paragraph()
            continue
        if stripped.startswith("## "):
            flush_paragraph()
            story.extend([CondPageBreak(36 * mm), Paragraph(inline_markup(stripped[3:]), styles["H2"])])
        elif stripped.startswith("### "):
            flush_paragraph()
            story.extend([CondPageBreak(22 * mm), Paragraph(inline_markup(stripped[4:]), styles["H3"])])
        elif re.match(r"^- ", stripped):
            flush_paragraph()
            story.append(Paragraph(inline_markup(stripped[2:]), styles["BulletMM"], bulletText="-"))
        elif re.match(r"^\d+\. ", stripped):
            flush_paragraph()
            number, body = stripped.split(". ", 1)
            story.append(Paragraph(inline_markup(body), styles["NumberMM"], bulletText=number + "."))
        else:
            paragraph_lines.append(stripped)
    flush_paragraph()
    flush_table()
    return story


def build():
    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    source_text = SOURCE.read_text(encoding="utf-8")
    if any(ord(char) > 127 for char in source_text):
        raise ValueError("The PRD source must use ASCII characters only for reliable PDF rendering.")
    doc = PRDDocTemplate(str(OUTPUT))
    toc = TableOfContents()
    toc.levelStyles = [
        ParagraphStyle(name="TOC0", fontName=BOLD, fontSize=9.2, leading=15, textColor=NAVY, leftIndent=0, firstLineIndent=0, spaceBefore=2),
        ParagraphStyle(name="TOC1", fontName=REGULAR, fontSize=8.2, leading=12, textColor=MUTED, leftIndent=12, firstLineIndent=0),
    ]
    story = cover_story(doc)
    story += [Paragraph("Contents", styles["H2"]), Spacer(1, 5), toc, PageBreak()]
    story += parse_markdown(source_text, doc)
    doc.multiBuild(story)
    print(OUTPUT)


if __name__ == "__main__":
    build()
