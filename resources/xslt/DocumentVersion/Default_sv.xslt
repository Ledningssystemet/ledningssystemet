<?xml version="1.0" encoding="UTF-8" ?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:xls="http://www.w3.org/1999/XSL/Transform">
    <xsl:template match="/">
        <html>
            <head>
                <style type="text/css">
                    html {
                        20mm 20mm 25mm 20mm;
                    }
                    body {
                        font-family: "Area Normal", sans-serif;
                        font-size: 10pt;
                    }
                    li              { display: list-item }
                    head            { display: none }
                    table           { display: table }
                    tr              { display: table-row }
                    thead           { display: table-header-group }
                    tbody           { display: table-row-group }
                    tfoot           { display: table-footer-group }
                    col             { display: table-column }
                    colgroup        { display: table-column-group }
                    td, th          { display: table-cell }
                    caption         { display: table-caption }
                    th              { font-weight: bolder; text-align: center}
                    caption         { text-align: center }
                    body            { margin: 8px }
                    .header-1       { font-size: 2em; margin: .67em 0;  }
                    .header-2       { font-size: 1.5em; margin: .75em 0;  }
                    .header-3       { font-size: 1.17em; margin: .83em 0 }
                    .header-4       { margin: 1.12em 0 }
                    .header-5       { font-size: .83em; margin: 1.5em 0 }
                    .header-6       { font-size: .75em; margin: 1.67em 0 }
                    .header,
                    strong          { font-weight: bolder;}
                    p,blockquote,   { margin: 1.12em 0 }
                    blockquote      { margin-left: 40px; margin-right: 40px }
                    i, cite, em,
                    var, address    { font-style: italic }
                    pre, tt, code,
                    kbd, samp       { font-family: monospace }
                    pre             { white-space: pre }
                    big             { font-size: 1.17em }
                    small, sub, sup { font-size: .83em }
                    sub             { vertical-align: sub }
                    sup             { vertical-align: super }
                    table           { border-spacing: 2px; }
                    thead, tbody,
                    tfoot           { vertical-align: middle }
                    td, th, tr      { vertical-align: inherit }
                    s, strike, del  { text-decoration: line-through }
                    hr              { border: 1px inset }
                    ol, ul, dir,
                    menu, dd        { margin-left: 0; }
                    ol              { list-style-type: decimal }
                    ol ul, ul ol,
                    ul ul, ol ol    { margin-top: 0; margin-bottom: 0 }
                    u, ins          { text-decoration: underline }
                    .header         { page-break-after: avoid }
                    ul, ol, dl      { page-break-before: avoid }
                    footer {
                        width: 100%;
                        border-top: 1px solid #888;
                        position: fixed;
                        bottom: 0;
                        color: #888;
                        text-align: center;
                        padding-top: 5mm;
                        margin-top: 10mm;
                        font-size: 8pt;
                        font-style: italic;
                    }

                    table.metadata {
                        width: 100%;
                        border-collapse: collapse;
                        margin-bottom: 10mm;
                        color: #888;
                    }

                    ul.checklist {
                        list-style: none;
                        padding-left: 0;
                        margin-left: 5mm;
                    }

                    ul.checklist li{
                        display: flex;
                        margin-bottom: 5px;
                    }

                    ul.checklist div.checkbox {
                        display: inline-flex;
                        height: 1em;
                        border-radius: 5px;
                        border: 1px solid #888;
                        width: 1em;
                        height: 1em;
                        margin-right: 5px;
                        position: relative;
                        top: 2px;
                    }

                </style>
            </head>
            <body>
                <footer>
                    Utskrivet dokument är inte säkert gällande. Se den digitala versionen för aktuell information.
                </footer>
                <xsl:apply-templates/>
            </body>
        </html>
    </xsl:template>

    <xsl:template match="document">
        <table class="metadata">
            <tr>
                <td>
                    <strong>Version:</strong><p><xsl:value-of select="doc_major"/>.<xsl:value-of select="doc_minor"/><xsl:if test="draft='true'"> (Utkast)</xsl:if></p>
                </td>
                <td>
                    <strong>Författare:</strong><p><xsl:value-of select="doc_author"/></p>
                </td>
                <td>
                    <strong>Fastställd av:</strong><p><xsl:if test="draft='true'">- Ej fastställd -</xsl:if><xsl:if test="draft!='true'"><xsl:value-of select="doc_approver"/></xsl:if></p>
                </td>
            </tr>
        </table>
        <xsl:apply-templates select="blocks"/>
    </xsl:template>

    <xsl:template match="blocks[item]">
        <xsl:apply-templates select="item"/>
    </xsl:template>

    <xsl:template match="item[type/text()='paragraph']">
        <p><xsl:value-of select="data/text" disable-output-escaping="yes" /></p>
    </xsl:template>

    <xsl:template match="item[type/text()='header']">
        <span class="header header-{data/level}"><xsl:value-of select="data/text" disable-output-escaping="yes" /></span>
    </xsl:template>

    <xsl:template match="item[type/text()='list' and data/style/text()='unordered']">
        <ul>
            <xsl:for-each select="data/items/item">
                <li>
                    <xsl:value-of select="content" disable-output-escaping="yes" />
                    <ul><xsl:apply-templates select="items"/></ul>
                </li>
            </xsl:for-each>
        </ul>
    </xsl:template>

    <xsl:template match="item[type/text()='list' and data/style/text()='ordered']">
        <ol>
            <xsl:for-each select="data/items/item">
                <li>
                    <xsl:value-of select="content" disable-output-escaping="yes" />
                    <ol><xsl:apply-templates select="items"/></ol>
                </li>
            </xsl:for-each>
        </ol>
    </xsl:template>

    <xsl:template match="item[type/text()='list' and data/style/text()='checklist']">
        <ul class="checklist">
            <xsl:for-each select="data/items/*">
                <li>
                    <div class="checkbox checked-{ meta/checked }"></div>
                    <span><xsl:value-of select="./content" disable-output-escaping="yes" /></span>
                </li>
            </xsl:for-each>
        </ul>

    </xsl:template>

    <xsl:template match="item[content]">
        <li>
            <xsl:value-of select="content" disable-output-escaping="yes" />
        </li>
    </xsl:template>

    <xsl:template match="items">
        <xsl:apply-templates select="item"/>
    </xsl:template>
</xsl:stylesheet>
