using System;
using System.Collections.Generic;
using System.Drawing;
using System.Drawing.Printing;
using System.Text.RegularExpressions;

public class KasirFlowThermalPrint
{
    public string PrinterName { get; set; }
    public string Content { get; set; }
    public int PaperWidthMm { get; set; }

    private const string MarkTitle = "@@T@@";
    private const string MarkCenter = "@@C@@";

    private List<string> _lines;
    private Font _fontBody;
    private Font _fontTitle;
    private int _lineIndex;
    private float _drawLeft;
    private float _drawWidth;
    private float _drawTop;

    public void Run()
    {
        int maxCols = PaperWidthMm >= 80 ? 48 : 24;
        int paperUnits = PaperWidthMm >= 80 ? 315 : 220;
        float bodySize = PaperWidthMm >= 80 ? 10f : 8.5f;
        float titleSize = PaperWidthMm >= 80 ? 12f : 11f;

        _lines = WrapContent(Content ?? string.Empty, maxCols);

        using (var doc = new PrintDocument())
        {
            doc.PrinterSettings.PrinterName = PrinterName;
            doc.DocumentName = "KasirFlow";
            doc.PrintController = new StandardPrintController();

            _fontBody = new Font("Courier New", bodySize, FontStyle.Regular, GraphicsUnit.Point);
            _fontTitle = new Font("Courier New", titleSize, FontStyle.Bold, GraphicsUnit.Point);

            int pageH = EstimatePageHeight(paperUnits);
            var ps = new PaperSize("Receipt58", paperUnits, pageH);

            doc.DefaultPageSettings.PaperSize = ps;
            doc.DefaultPageSettings.Margins = new Margins(0, 0, 0, 0);
            doc.OriginAtMargins = false;

            doc.PrintPage += OnPrintPage;
            doc.Print();

            _fontBody.Dispose();
            _fontTitle.Dispose();
        }
    }

    private int EstimatePageHeight(int paperUnits)
    {
        float total = 24f;
        foreach (var raw in _lines)
        {
            var kind = ClassifyLine(raw);
            var font = kind == LineKind.Title ? _fontTitle : _fontBody;
            total += font.GetHeight(96f) + 2f;
        }
        return Math.Max(300, (int)Math.Ceiling(total));
    }

    private enum LineKind { Empty, Title, Center, Normal }

    private static LineKind ClassifyLine(string line)
    {
        if (string.IsNullOrWhiteSpace(line)) return LineKind.Empty;
        if (line.StartsWith(MarkTitle, StringComparison.Ordinal)) return LineKind.Title;
        if (line.StartsWith(MarkCenter, StringComparison.Ordinal)) return LineKind.Center;
        return LineKind.Normal;
    }

    private static string StripMarker(string line)
    {
        if (line.StartsWith(MarkTitle, StringComparison.Ordinal)) return line.Substring(MarkTitle.Length);
        if (line.StartsWith(MarkCenter, StringComparison.Ordinal)) return line.Substring(MarkCenter.Length);
        return line ?? string.Empty;
    }

    private static List<string> WrapContent(string content, int maxCols)
    {
        var result = new List<string>();
        var normalized = content.Replace("\r\n", "\n").Replace('\r', '\n');
        foreach (var raw in normalized.Split('\n'))
        {
            var line = raw ?? string.Empty;
            string prefix = string.Empty;
            if (line.StartsWith(MarkTitle, StringComparison.Ordinal))
            {
                prefix = MarkTitle;
                line = line.Substring(MarkTitle.Length);
            }
            else if (line.StartsWith(MarkCenter, StringComparison.Ordinal))
            {
                prefix = MarkCenter;
                line = line.Substring(MarkCenter.Length);
            }

            if (line.Length > maxCols)
            {
                line = line.Substring(0, maxCols);
            }

            result.Add(prefix + line);
        }
        return result;
    }

    private void ResolveDrawArea(PrintPageEventArgs e)
    {
        var paper = e.PageSettings.PaperSize;
        float hardX = Math.Max(0f, e.PageSettings.HardMarginX);
        float hardY = Math.Max(0f, e.PageSettings.HardMarginY);

        _drawLeft = hardX + 6f;
        _drawTop = hardY + 2f;
        _drawWidth = paper.Width - (_drawLeft * 2f);

        var printable = e.PageSettings.PrintableArea;
        if (printable.Width > 20f && printable.Width < _drawWidth)
        {
            _drawLeft = printable.X + 2f;
            _drawWidth = printable.Width - 4f;
            _drawTop = printable.Y + 2f;
        }

        if (_drawWidth < 80f)
        {
            _drawLeft = hardX + 4f;
            _drawWidth = Math.Max(80f, paper.Width - hardX - 20f);
        }
    }

    private void OnPrintPage(object sender, PrintPageEventArgs e)
    {
        if (_lineIndex == 0)
        {
            ResolveDrawArea(e);
        }

        float y = _drawTop;

        while (_lineIndex < _lines.Count)
        {
            var kind = ClassifyLine(_lines[_lineIndex]);
            if (kind == LineKind.Empty)
            {
                y += _fontBody.GetHeight(e.Graphics) * 0.5f;
                _lineIndex++;
                continue;
            }

            var font = kind == LineKind.Title ? _fontTitle : _fontBody;
            float lineH = font.GetHeight(e.Graphics) + 2f;

            if (y + lineH > e.PageSettings.PaperSize.Height - 6f)
            {
                e.HasMorePages = true;
                return;
            }

            DrawReceiptLine(e.Graphics, _lines[_lineIndex], kind, font, y, lineH);
            y += lineH;
            _lineIndex++;
        }

        e.HasMorePages = false;
    }

    private void DrawReceiptLine(Graphics g, string line, LineKind kind, Font font, float y, float lineH)
    {
        var rect = new RectangleF(_drawLeft, y, _drawWidth, lineH + 2f);
        var text = StripMarker(line).Trim();
        if (text.Length == 0) return;

        using (var sf = new StringFormat(StringFormat.GenericTypographic))
        {
            sf.FormatFlags = StringFormatFlags.NoWrap | StringFormatFlags.MeasureTrailingSpaces;
            sf.Trimming = StringTrimming.None;

            if (kind == LineKind.Title || kind == LineKind.Center)
            {
                sf.Alignment = StringAlignment.Center;
                sf.LineAlignment = StringAlignment.Near;
                g.DrawString(text, font, Brushes.Black, rect, sf);
                return;
            }

            if (IsAmountLine(text))
            {
                sf.Alignment = StringAlignment.Far;
                sf.LineAlignment = StringAlignment.Near;
                g.DrawString(text, font, Brushes.Black, rect, sf);
                return;
            }

            var split = Regex.Match(line, @"^(.*?)(\s+)(Rp\s.+)$");
            if (split.Success)
            {
                var label = split.Groups[1].Value;
                var amount = split.Groups[3].Value.Trim();

                using (var sfLeft = new StringFormat(StringFormat.GenericTypographic))
                {
                    sfLeft.Alignment = StringAlignment.Near;
                    sfLeft.FormatFlags = StringFormatFlags.NoWrap;
                    sfLeft.Trimming = StringTrimming.EllipsisCharacter;
                    g.DrawString(label, font, Brushes.Black, rect, sfLeft);
                }

                using (var sfRight = new StringFormat(StringFormat.GenericTypographic))
                {
                    sfRight.Alignment = StringAlignment.Far;
                    sfRight.FormatFlags = StringFormatFlags.NoWrap;
                    g.DrawString(amount, font, Brushes.Black, rect, sfRight);
                }
                return;
            }

            sf.Alignment = StringAlignment.Near;
            g.DrawString(text, font, Brushes.Black, rect, sf);
        }
    }

    private static bool IsAmountLine(string trimmed)
    {
        return trimmed.StartsWith("Rp ", StringComparison.Ordinal)
            || trimmed.StartsWith("Rp.", StringComparison.Ordinal);
    }
}
