<?xml version="1.0" encoding="UTF-8"?>

<xsl:stylesheet
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:t="http://www.tei-c.org/ns/1.0"
	xmlns:eg="http://www.tei-c.org/ns/Examples"
	exclude-result-prefixes="t eg"
	version="1.0">

	<xsl:output method="xml" indent="no" />

	<xsl:template match="/">
		<xsl:text disable-output-escaping="yes">&lt;!DOCTYPE xml &gt;</xsl:text>
		<TEI xmlns='http://www.tei-c.org/ns/1.0'><!--
			--><xsl:apply-templates select="node()|@*" />
			<xsl:call-template name="notes" />
			<xsl:call-template name="attrs" /><!--
		--></TEI>
	</xsl:template>

	<xsl:template match="node()[not(self::text())][not(self::t:note)][not(self::t:TEI)]">
		<xsl:copy>
			<xsl:apply-templates select="node()|@*" />
		</xsl:copy>
	</xsl:template>

	<!-- Keep attributes -->
	<xsl:template match="@*">
		<xsl:copy />
	</xsl:template>

	<!-- Do nothing - or //t:note ?? -->
	<xsl:template match="t:note" />

	<xsl:template match="text()">
		<xsl:copy />
	</xsl:template>

	<!-- Gather attribute values separately -->
	<xsl:template name="attrs" match="TEI/text">
		<xsl:element name="text">
			<!-- //@* -->
			<xsl:for-each select="//@*">
				<xsl:value-of select="." />
				<xsl:text> – </xsl:text>
			</xsl:for-each>
		</xsl:element>
	</xsl:template>

	<!-- Gather notes separately -->	
	<xsl:template name="notes" match="TEI/text">
		<xsl:element name="text">
		<xsl:element name="noteGrp">
			<xsl:for-each select="//t:note">
				<xsl:element name="{local-name()}">
					<xsl:copy-of select="@*"/>
					<xsl:apply-templates />
				</xsl:element>
				<xsl:text> </xsl:text>
			</xsl:for-each>
		</xsl:element>
		</xsl:element>
	</xsl:template>

</xsl:stylesheet>
