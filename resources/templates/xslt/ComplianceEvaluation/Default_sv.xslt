<?xml version="1.0" encoding="UTF-8" ?>
<!--
    {
        "name":"Utvärderingsrapport",
        "papersize": "A4",
        "language":"sv",
        "orientation": "portrait"
    }
-->
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
  <xsl:output method="html" indent="yes" />
  <xsl:template match="/">
    <html>
      <head>
        <title>Compliance Evaluation Report</title>
        <style>
          body { font-family: Arial, sans-serif; }
          h1 { color: #333; }
          table { width: 100%; border-collapse: collapse; margin-top: 20px; }
          th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
          th { background-color: #f2f2f2; }
        </style>
      </head>
      <body>
        <h1>Compliance Evaluation Report</h1>
        <table>
          <tr>
            <th>Evaluation ID</th>
            <th>Status</th>
            <th>Details</th>
          </tr>
          <xsl:for-each select="ComplianceEvaluations/Evaluation">
            <tr>
              <td><xsl:value-of select="ID" /></td>
              <td><xsl:value-of select="Status" /></td>
              <td><xsl:value-of select="Details" /></td>
            </tr>
          </xsl:for-each>
        </table>
      </body>
    </html>
  </xsl:template>
</xsl:stylesheet>
