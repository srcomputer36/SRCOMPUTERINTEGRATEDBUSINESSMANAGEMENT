# -*- coding: utf-8 -*-
"""
নূরানী বাংলা শিক্ষা (নার্সারি / শিশু শ্রেণি) — হাতের লেখা অনুশীলন খাতা জেনারেটর
=================================================================================
বইয়ের পাঠক্রম অনুযায়ী প্রতিটি পাতায়:
  * উপরে  : প্রতিষ্ঠানের নাম + ছাত্রের নাম / শ্রেণি / রোল / তারিখ লেখার ঘর
  * মাঝখানে: পাঠের নাম ও আজকের লেখার বিষয় (হরফ / শব্দ) বড় করে দেখানো
  * নিচে  : পুরো খালি ঘর (হরফের জন্য) অথবা দুই-লাইন খাতা (শব্দের জন্য)

ব্যবহার:
    python3 make_worksheets.py --name "আপনার মাদরাসার নাম" --address "ঠিকানা"
    python3 make_worksheets.py --box 2.0            # ঘরের আকার সেমি-তে
    python3 make_worksheets.py --out khata.pdf

নির্ভরতা:  pip install pymupdf
"""
import argparse
import os
import sys

import pymupdf

HERE = os.path.dirname(os.path.abspath(__file__))
FONT_DIR = os.path.join(HERE, "fonts")

# ---------------------------------------------------------------------------
# পাঠক্রম (বই থেকে)
# ---------------------------------------------------------------------------
NBSP = "\u00a0"  # একা স্বরচিহ্ন/চিহ্ন দেখানোর জন্য (ডটেড সার্কেল এড়াতে)

SWARA = ["অ", "আ", "ই", "ঈ", "উ", "ঊ", "ঋ", "এ", "ঐ", "ও", "ঔ"]
BYANJAN_ROWS = [
    ["ক", "খ", "গ", "ঘ", "ঙ"],
    ["চ", "ছ", "জ", "ঝ", "ঞ"],
    ["ট", "ঠ", "ড", "ঢ", "ণ"],
    ["ত", "থ", "দ", "ধ", "ন"],
    ["প", "ফ", "ব", "ভ", "ম"],
    ["য", "র", "ল", "শ", "ষ"],
    ["স", "হ", "ড়", "ঢ়", "য়"],
    ["ৎ", NBSP + "ং", NBSP + "ঃ", NBSP + "ঁ"],
]
BANAN_WORDS = [
    "ঘর", "বই", "গম", "জগ", "মগ",
    "মই", "ধন", "মন", "রব", "খই",
    "দই", "পথ", "ঈদ", "ইট", "চক",
    "এক", "জন", "কই", "নখ", "উট",
    "রহম", "কদম", "জনম", "মরণ", "পবন",
]
KAR_SIGNS = [NBSP + "া", NBSP + "ি", NBSP + "ী", NBSP + "ু", NBSP + "ূ"]
KAR_NAMES = ["আ-কার", "ই-কার", "ঈ-কার", "উ-কার", "ঊ-কার"]
KAR_TABLE = [
    ["কা", "কি", "কী", "কু", "কূ"],
    ["খা", "খি", "খী", "খু", "খূ"],
    ["গা", "গি", "গী", "গু", "গূ"],
    ["ঘা", "ঘি", "ঘী", "ঘু", "ঘূ"],
]
KAR_WORDS = ["বাবা", "কাবা", "চিনি", "কিনি", "বীর", "তীর", "মধু", "কদু", "রূপ", "কূপ"]
AKAR_LETTERS = ["কা", "খা", "গা", "ঘা", "চা", "ছা", "জা", "টা", "ঠা", "ডা"]
AKAR_WORDS = ["আম", "জাম", "আতা", "পাকা", "মজা", "তারা", "ধান", "মাছ", "গাছ", "পাতা", "আমার", "নামাজ", "দরজা"]
DAYS = ["শনিবার", "রবিবার", "সোমবার", "মঙ্গলবার", "বুধবার", "বৃহস্পতিবার", "শুক্রবার"]
MONTHS = ["বৈশাখ", "জ্যৈষ্ঠ", "আষাঢ়", "শ্রাবণ", "ভাদ্র", "আশ্বিন",
          "কার্তিক", "অগ্রহায়ণ", "পৌষ", "মাঘ", "ফাল্গুন", "চৈত্র"]
TREES = ["আম গাছ", "জাম গাছ", "কাঁঠাল গাছ", "লিচু গাছ", "তাল গাছ", "নারিকেল গাছ"]

BN_DIGITS = str.maketrans("0123456789", "০১২৩৪৫৬৭৮৯")


def bn(n):
    return str(n).translate(BN_DIGITS)


def build_plan():
    """প্রতিটি পাতার (lesson, title, items, style) — style: 'grid' বা 'lines'."""
    plan = []
    L1, L2, L3, L4 = "প্রথম পাঠ", "দ্বিতীয় পাঠ", "তৃতীয় পাঠ", "চতুর্থ পাঠ"

    # ---- প্রথম পাঠ: স্বরবর্ণ
    plan.append((L1, "স্বরবর্ণ", SWARA[0:4], "grid"))
    plan.append((L1, "স্বরবর্ণ", SWARA[4:7], "grid"))
    plan.append((L1, "স্বরবর্ণ", SWARA[7:11], "grid"))
    plan.append((L1, "স্বরবর্ণ (পুনরালোচনা)", SWARA, "grid"))
    # ---- প্রথম পাঠ: ব্যঞ্জনবর্ণ
    for row in BYANJAN_ROWS:
        plan.append((L1, "ব্যঞ্জনবর্ণ", row, "grid"))
    plan.append((L1, "ব্যঞ্জনবর্ণ (পুনরালোচনা ১)", sum(BYANJAN_ROWS[0:4], []), "grid"))
    plan.append((L1, "ব্যঞ্জনবর্ণ (পুনরালোচনা ২)", sum(BYANJAN_ROWS[4:8], []), "grid"))
    # ---- দ্বিতীয় পাঠ: বানান করে পড়ি
    plan.append((L2, "বানান করে পড়ি ও লিখি", BANAN_WORDS[0:10], "lines"))
    plan.append((L2, "বানান করে পড়ি ও লিখি", BANAN_WORDS[10:20], "lines"))
    plan.append((L2, "বানান করে পড়ি ও লিখি", BANAN_WORDS[20:25], "lines"))
    # ---- তৃতীয় পাঠ: স্বরচিহ্ন
    plan.append((L3, "পাঁচটি স্বরচিহ্ন", KAR_SIGNS, "grid", KAR_NAMES))
    for row in KAR_TABLE:
        plan.append((L3, "স্বরচিহ্নের ব্যবহার শিখি", row, "grid"))
    plan.append((L3, "আ-কার ( া) দিয়ে শব্দ শিখি", AKAR_LETTERS, "grid"))
    plan.append((L3, "আ-কার দিয়ে বানান করে লিখি", AKAR_WORDS[0:7], "lines"))
    plan.append((L3, "আ-কার দিয়ে বানান করে লিখি", AKAR_WORDS[7:13], "lines"))
    plan.append((L3, "স্বরচিহ্ন দিয়ে শব্দ গঠন করি", KAR_WORDS, "lines"))
    # ---- চতুর্থ পাঠ: মুখে মুখে নামগুলো শিখি
    plan.append((L4, "সাত দিনের নাম", DAYS, "lines"))
    plan.append((L4, "বারো মাসের নাম", MONTHS[0:6], "lines"))
    plan.append((L4, "বারো মাসের নাম", MONTHS[6:12], "lines"))
    plan.append((L4, "গাছের নাম", TREES, "lines"))
    # ---- অতিরিক্ত খালি পাতা
    plan.append(("অতিরিক্ত", "খালি ঘর (যেকোনো হরফ অনুশীলনের জন্য)", [], "grid"))
    plan.append(("অতিরিক্ত", "খালি দুই-লাইন খাতা (যেকোনো শব্দ অনুশীলনের জন্য)", [], "lines"))
    return plan


# ---------------------------------------------------------------------------
# অঙ্কন
# ---------------------------------------------------------------------------
CM = 72 / 2.54
PAGE_W, PAGE_H = 595.0, 842.0  # A4
MARGIN = 1.4 * CM

INK = (0.1, 0.1, 0.1)
ACCENT = (0.80, 0.10, 0.25)      # লাল-গোলাপি (বইয়ের ব্যানারের মতো)
ACCENT2 = (0.05, 0.45, 0.30)     # সবুজ
GRID = (0.55, 0.65, 0.85)        # হালকা নীল ঘর
GRID_LIGHT = (0.78, 0.84, 0.94)
RULE = (0.45, 0.55, 0.80)
RULE_MID = (0.75, 0.80, 0.92)


class Writer:
    def __init__(self, args):
        self.args = args
        self.doc = pymupdf.open()
        self.arch = pymupdf.Archive(FONT_DIR)
        self.css = (
            "@font-face{font-family:hind;src:url(HindSiliguri-Regular.ttf);}"
            "@font-face{font-family:hind;font-weight:bold;src:url(HindSiliguri-Bold.ttf);}"
            "@font-face{font-family:noto;src:url(NotoSansBengali.ttf);}"
            "*{font-family:hind;color:#1a1a1a;margin:0;padding:0;}"
            ".c{text-align:center;}"
            ".sign{font-family:noto;}"
        )
        self.page_no = 0

    # -- helpers ----------------------------------------------------------
    def html(self, page, rect, html, scale_low=0.5):
        page.insert_htmlbox(rect, html, css=self.css, archive=self.arch, scale_low=scale_low)

    def header(self, page):
        a = self.args
        y = MARGIN * 0.8
        name = a.name.strip() if a.name else ""
        if name:
            name_html = f'<div class="c" style="font-size:19px;font-weight:bold;color:#0b3d2e">{name}</div>'
            if a.address:
                name_html += f'<div class="c" style="font-size:10.5px">{a.address}</div>'
        else:
            name_html = ('<div class="c" style="font-size:14px;font-weight:bold">'
                         'প্রতিষ্ঠানের নাম : ................................................................</div>')
        name_html += ('<div class="c" style="font-size:11px;color:#8a1030">'
                      'নূরানী বাংলা শিক্ষা &nbsp;•&nbsp; নার্সারি (শিশু শ্রেণি) &nbsp;•&nbsp; হাতের লেখা অনুশীলন খাতা</div>')
        self.html(page, pymupdf.Rect(MARGIN, y, PAGE_W - MARGIN, y + 62), name_html)
        y += 62
        # ছাত্রের তথ্যের ঘর
        box = pymupdf.Rect(MARGIN, y, PAGE_W - MARGIN, y + 36)
        page.draw_rect(box, color=GRID, width=0.8, radius=0.08)
        info = ('<div style="font-size:11.5px;line-height:1.55">'
                '<b>নাম :</b> ....................................................................&nbsp;&nbsp; '
                '<b>শ্রেণি :</b> নার্সারি (শিশু)&nbsp;&nbsp; <b>রোল :</b> ..............<br>'
                '<b>তারিখ :</b> ........ / ........ / ................&nbsp;&nbsp;&nbsp; '
                '<b>বার :</b> ............................&nbsp;&nbsp;&nbsp; '
                '<b>শিক্ষকের স্বাক্ষর :</b> ........................</div>')
        self.html(page, pymupdf.Rect(box.x0 + 7, box.y0 + 3, box.x1 - 7, box.y1 - 1), info)
        return box.y1 + 8

    def lesson_banner(self, page, y, lesson, title, items, labels=None):
        # ব্যানার
        bw = 0.62 * (PAGE_W - 2 * MARGIN)
        bx0 = (PAGE_W - bw) / 2
        banner = pymupdf.Rect(bx0, y, bx0 + bw, y + 26)
        page.draw_rect(banner, color=None, fill=ACCENT, radius=0.5)
        self.html(page, pymupdf.Rect(banner.x0, banner.y0 + 1, banner.x1, banner.y1),
                  f'<div class="c" style="font-size:14px;font-weight:bold;color:#ffffff">{lesson} &nbsp;—&nbsp; {title}</div>')
        y = banner.y1 + 6
        if not items:
            return y
        # আজকের লেখা — মডেল হরফ/শব্দ
        n = len(items)
        maxlen = max(len(s) for s in items)
        if maxlen <= 2 and n <= 5:
            fs = 40
        elif maxlen <= 2:
            fs = 30
        elif n <= 7:
            fs = 26
        else:
            fs = 22
        self.html(page, pymupdf.Rect(MARGIN, y, PAGE_W - MARGIN, y + 16),
                  '<div class="c" style="font-size:11px;color:#8a1030">আজকের লেখা — দেখে দেখে নিচের ঘরে লিখি</div>')
        y += 17
        per_row = n if n <= 7 else (n + 1) // 2
        cell_w = max(fs * 1.35, fs * 0.62 * maxlen + 18)
        cell_h = fs * 1.45 + (14 if labels else 0)
        rows = [items[i:i + per_row] for i in range(0, n, per_row)]
        for r, row in enumerate(rows):
            total_w = cell_w * len(row)
            x = (PAGE_W - total_w) / 2
            yy = y + r * cell_h
            for j, s in enumerate(row):
                i = r * per_row + j
                rect = pymupdf.Rect(x + j * cell_w, yy, x + (j + 1) * cell_w, yy + cell_h)
                page.draw_rect(rect, color=GRID, width=0.8, fill=(0.97, 0.98, 1.0))
                cls = "sign" if s.startswith(NBSP) else ""
                lab = f'<div style="font-size:10px;color:#555;line-height:1.1">{labels[i]}</div>' if labels else ""
                self.html(page, pymupdf.Rect(rect.x0, rect.y0 + 1, rect.x1, rect.y1),
                          f'<div class="c {cls}" style="font-size:{fs}px;font-weight:bold;color:#102a6b;line-height:1.3">{s}</div>{lab}')
        return y + cell_h * len(rows) + 10

    def grid_body(self, page, y_top, y_bottom):
        box = self.args.box * CM
        avail_w = PAGE_W - 2 * MARGIN
        cols = int(avail_w // box)
        rows = int((y_bottom - y_top) // box)
        gw, gh = cols * box, rows * box
        x0 = (PAGE_W - gw) / 2
        y0 = y_top + ((y_bottom - y_top) - gh) / 2
        # হালকা মাত্রা-রেখা (ঘরের উপরের ২৫% এ) — বাংলা হাতের লেখার গাইড
        for r in range(rows):
            yy = y0 + r * box + box * 0.28
            page.draw_line((x0, yy), (x0 + gw, yy), color=GRID_LIGHT, width=0.5, dashes="[2 2] 0")
        for c in range(cols + 1):
            xx = x0 + c * box
            page.draw_line((xx, y0), (xx, y0 + gh), color=GRID, width=0.9)
        for r in range(rows + 1):
            yy = y0 + r * box
            page.draw_line((x0, yy), (x0 + gw, yy), color=GRID, width=0.9)
        page.draw_rect(pymupdf.Rect(x0, y0, x0 + gw, y0 + gh), color=RULE, width=1.4)

    def lines_body(self, page, y_top, y_bottom):
        """দুই-লাইন খাতা: প্রতি সেটে উপরের মাত্রা-রেখা, মাঝের হালকা রেখা, নিচের বেসলাইন।"""
        line_h = self.args.box * CM * 0.9      # এক সেটের লেখার উচ্চতা
        gap = line_h * 0.55                    # সেটের মাঝে ফাঁক
        x0, x1 = MARGIN, PAGE_W - MARGIN
        y = y_top + 4
        while y + line_h <= y_bottom:
            page.draw_line((x0, y), (x1, y), color=RULE, width=1.0)
            page.draw_line((x0, y + line_h * 0.3), (x1, y + line_h * 0.3), color=RULE_MID, width=0.5, dashes="[2 2] 0")
            page.draw_line((x0, y + line_h), (x1, y + line_h), color=RULE, width=1.0)
            y += line_h + gap

    def footer(self, page):
        y = PAGE_H - MARGIN * 0.9
        self.html(page, pymupdf.Rect(MARGIN, y, PAGE_W - MARGIN, y + 16),
                  '<div style="font-size:10.5px"><b>অভিভাবকের স্বাক্ষর :</b> ........................................</div>')
        self.html(page, pymupdf.Rect(MARGIN, y, PAGE_W - MARGIN, y + 16),
                  '<div style="font-size:10.5px;text-align:right">পৃষ্ঠা ' + bn(self.page_no) + '</div>')
        self.html(page, pymupdf.Rect(MARGIN, y + 13, PAGE_W - MARGIN, y + 26),
                  '<div class="c" style="font-size:8.5px;color:#777">নূরানী তা’লীমুল কুরআন বোর্ড বাংলাদেশ-এর “নূরানী বাংলা শিক্ষা” (নার্সারি) বইয়ের পাঠক্রম অনুসারে প্রস্তুত অনুশীলন খাতা</div>')
        return y - 8

    # -- pages ------------------------------------------------------------
    def worksheet(self, lesson, title, items, style, labels=None):
        self.page_no += 1
        page = self.doc.new_page(width=PAGE_W, height=PAGE_H)
        y = self.header(page)
        y = self.lesson_banner(page, y, lesson, title, items, labels)
        y_bottom = self.footer(page) - 4
        if style == "grid":
            self.grid_body(page, y, y_bottom)
        else:
            self.lines_body(page, y, y_bottom)

    def cover_and_index(self, plan):
        self.page_no += 1
        page = self.doc.new_page(width=PAGE_W, height=PAGE_H)
        a = self.args
        name = a.name.strip() if a.name else "প্রতিষ্ঠানের নাম : ............................................................"
        top = (f'<div class="c" style="font-size:22px;font-weight:bold;color:#0b3d2e">{name}</div>'
               + (f'<div class="c" style="font-size:11px">{a.address}</div>' if a.address else "")
               + '<div class="c" style="font-size:30px;font-weight:bold;color:#8a1030;margin-top:14px">হাতের লেখা অনুশীলন খাতা</div>'
               '<div class="c" style="font-size:15px;margin-top:4px">নূরানী বাংলা শিক্ষা — নার্সারি (শিশু শ্রেণি)</div>'
               '<div class="c" style="font-size:11px;color:#555;margin-top:2px">(নূরানী তা’লীমুল কুরআন বোর্ড বাংলাদেশ-এর বইয়ের পাঠক্রম অনুযায়ী সাজানো)</div>')
        self.html(page, pymupdf.Rect(MARGIN, MARGIN, PAGE_W - MARGIN, MARGIN + 130), top)
        box = pymupdf.Rect(MARGIN + 40, MARGIN + 135, PAGE_W - MARGIN - 40, MARGIN + 190)
        page.draw_rect(box, color=GRID, width=0.8, radius=0.1)
        self.html(page, pymupdf.Rect(box.x0 + 10, box.y0 + 4, box.x1 - 10, box.y1),
                  '<div style="font-size:12px;line-height:1.7"><b>ছাত্র/ছাত্রীর নাম :</b> ........................................................................<br>'
                  '<b>শ্রেণি :</b> নার্সারি (শিশু) &nbsp;&nbsp; <b>রোল নং :</b> ................ &nbsp;&nbsp; <b>শিক্ষাবর্ষ :</b> ..................</div>')

        # সাজেশন / সূচি
        rows_html = []
        for i, p in enumerate(plan, start=2):
            lesson, title, items, style = p[0], p[1], p[2], p[3]
            shown = " ".join(items) if items else "—"
            kind = "খালি ঘর" if style == "grid" else "দুই-লাইন"
            rows_html.append(
                f'<tr><td style="text-align:center">{bn(i)}</td><td>{lesson}</td><td>{title}</td>'
                f'<td class="{"sign" if any(s.startswith(NBSP) for s in items) else ""}">{shown}</td><td style="text-align:center">{kind}</td></tr>')
        table = ('<div style="font-size:14px;font-weight:bold;color:#8a1030;margin-bottom:3px">পাঠ-পরিকল্পনা ও সূচি (সাজেশন) — প্রতিদিন একটি পাতা</div>'
                 '<table style="width:100%;border-collapse:collapse;font-size:9.3px;line-height:1.35">'
                 '<tr style="background:#f3e3e8;font-weight:bold"><td style="text-align:center">পৃষ্ঠা</td><td>পাঠ</td><td>বিষয়</td><td>লেখার হরফ / শব্দ</td><td style="text-align:center">ধরন</td></tr>'
                 + "".join(rows_html) + "</table>")
        css_extra = self.css + "td{border:0.6px solid #9fb3d9;padding:1.5px 4px;}"
        page.insert_htmlbox(pymupdf.Rect(MARGIN, MARGIN + 200, PAGE_W - MARGIN, PAGE_H - MARGIN - 70),
                            table, css=css_extra, archive=self.arch, scale_low=0.4)
        tips = ('<div style="font-size:9.5px;line-height:1.45;color:#333">'
                '<b>শিক্ষকের জন্য নির্দেশনা:</b> বইয়ের “পাঠদান পদ্ধতি” অনুযায়ী প্রথমে চক-স্লেটে হরফের নাম বলে বলে হাত ঘুরিয়ে দেখাবেন, '
                'তারপর শিশুরা উপরের নমুনা দেখে দেখে খালি ঘরে লিখবে। প্রতিদিন অল্প অল্প করে — একটি পাতার বেশি নয়। '
                'ঘরের উপরের ডটেড রেখাটি মাত্রা টানার গাইড। কোনো চাপ নয়; উৎসাহ দিয়ে লেখাবেন।</div>')
        self.html(page, pymupdf.Rect(MARGIN, PAGE_H - MARGIN - 66, PAGE_W - MARGIN, PAGE_H - MARGIN - 10), tips)

    def build(self):
        plan = build_plan()
        self.cover_and_index(plan)
        for p in plan:
            self.worksheet(*p)
        self.doc.set_metadata({"title": "নূরানী বাংলা শিক্ষা — হাতের লেখা অনুশীলন খাতা",
                               "author": self.args.name or ""})
        self.doc.subset_fonts()
        self.doc.save(self.args.out, garbage=4, deflate=True)
        return len(self.doc)


def main():
    ap = argparse.ArgumentParser(description="নূরানী বাংলা শিক্ষা — হাতের লেখা খাতা PDF")
    ap.add_argument("--name", default="", help="মাদরাসা/প্রতিষ্ঠানের নাম")
    ap.add_argument("--address", default="", help="ঠিকানা (ঐচ্ছিক)")
    ap.add_argument("--box", type=float, default=2.0, help="ঘরের আকার (সেমি), ডিফল্ট ২.০")
    ap.add_argument("--out", default=os.path.join(HERE, "output", "Nurani_Bangla_Hater_Lekha_Khata.pdf"))
    args = ap.parse_args()
    os.makedirs(os.path.dirname(os.path.abspath(args.out)), exist_ok=True)
    n = Writer(args).build()
    print(f"তৈরি হয়েছে: {args.out}  ({n} পৃষ্ঠা)")


if __name__ == "__main__":
    sys.exit(main())
