<?php

class EchoHtmlEmailFormatter extends EchoEventFormatter {

	const PRIMARY_LINK_STYLE = 'cursor:pointer; text-align:center; text-decoration:none; padding:.45em 0.6em .45em; color:#D9EEF7; background:#3366BB; font-family: Arial, Helvetica, sans-serif;font-size: 13px;';
	const SECONDARY_LINK_STYLE = 'text-decoration: none;font-size: 10px;font-family: Arial, Helvetica, sans-serif; color: #808184;';

	protected function formatModel( EchoEventPresentationModel $model ) {
		$subject = $model->getSubjectMessage()->parse();

		$intro = $model->getHeaderMessage()->parse();

		$bodyMsg = $model->getBodyMessage();
		$summary = $bodyMsg ? $bodyMsg->parse() : '';

		$actions = array();

		$primaryLink = $model->getPrimaryLinkWithMarkAsRead();
		if ( $primaryLink ) {
			$actions[] = $this->renderLink( $primaryLink, self::PRIMARY_LINK_STYLE );
		}

		foreach ( array_filter( $model->getSecondaryLinks() ) as $secondaryLink ) {
			$actions[] = $this->renderLink( $secondaryLink, self::SECONDARY_LINK_STYLE );
		}

		$iconUrl = wfExpandUrl(
			EchoIcon::getRasterizedUrl( $model->getIconType(), $this->language->getCode() ),
			PROTO_CANONICAL
		);

		$body = $this->renderBody(
			$this->language,
			$iconUrl,
			$summary,
			implode( "&nbsp;&nbsp;", $actions ),
			$intro,
			$this->getFooter()
		);

		return array(
			'body' => $body,
			'subject' => $subject,
		);
	}

	private function renderBody( Language $lang, $emailIcon, $summary, $action, $intro, $footer ) {
		$alignStart = $lang->alignStart();
		$langCode = $lang->getCode();
		$langDir = $lang->getDir();

		$iconImgSrc = Sanitizer::encodeAttribute( $emailIcon );

		return <<< EOF
<html><head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<style>
		@media only screen and (max-width: 480px){
			table[id="email-container"]{max-width:600px !important; width:100% !important;}
		}
	</style>
</head><body>
<table cellspacing="0" cellpadding="0" border="0" width="100%" align="center" lang="{$langCode}" dir="{$langDir}">
<tr>
	<td bgcolor="#E6E7E8"><center>
		<br /><br />
		<table cellspacing="0" cellpadding="0" border="0" width="600" id="email-container">
			<tr>
				<td bgcolor="#FFFFFF" width="5%">&nbsp;</td>
				<td bgcolor="#FFFFFF" width="10%">&nbsp;</td>
				<td bgcolor="#FFFFFF" width="80%" style="line-height:40px;">&nbsp;</td>
				<td bgcolor="#FFFFFF" width="5%">&nbsp;</td>
			</tr><tr>
				<td bgcolor="#FFFFFF" rowspan="2">&nbsp;</td>
				<td bgcolor="#FFFFFF" align="center" valign="top" rowspan="2"><img src="{$iconImgSrc}" alt="" height="30" width="30"></td>
				<td bgcolor="#FFFFFF" align="{$alignStart}" style="font-family: Arial, Helvetica, sans-serif; font-size:13px; line-height:20px; color:#6D6E70;">{$intro}</td>
				<td bgcolor="#FFFFFF" rowspan="2">&nbsp;</td>
			</tr><tr>
				<td bgcolor="#FFFFFF" align="{$alignStart}" style="font-family: Arial, Helvetica, sans-serif; line-height: 20px; font-weight: 600;">
					<table cellspacing="0" cellpadding="0" border="0">
						<tr>
							<td bgcolor="#FFFFFF" align="{$alignStart}" style="font-family: Arial, Helvetica, sans-serif; padding-top: 8px; font-size:13px; font-weight: bold; color: #58585B;">
								{$summary}
							</td>
						</tr>
					</table>
					<table cellspacing="0" cellpadding="0" border="0">
						<tr>
							<td bgcolor="#FFFFFF" align="{$alignStart}" style="font-family: Arial, Helvetica, sans-serif; font-size:14px; padding-top: 25px;">
								{$action}
							</td>
						</tr>
					</table>
				</td>
			</tr><tr>
				<td bgcolor="#FFFFFF">&nbsp;</td>
				<td bgcolor="#FFFFFF">&nbsp;</td>
				<td bgcolor="#FFFFFF" style="line-height:40px;">&nbsp;</td>
				<td bgcolor="#FFFFFF">&nbsp;</td>
			</tr><tr>
				<td>&nbsp;</td>
				<td>&nbsp;</td>
				<td align="{$alignStart}" style="font-family: Arial, Helvetica, sans-serif; font-size:10px; line-height:13px; color:#6D6E70; padding:10px 20px;"><br />
					{$footer}
					<br /><br />
				</td>
				<td>&nbsp;</td>
			</tr><tr>
				<td colspan="4">&nbsp;</td>
			</tr>
		</table>
		<br><br></center>
	</td>
</tr>
</table>
</body></html>
EOF;
	}

	/**
	 * @return string
	 */
	public function getFooter() {
		global $wgEchoEmailFooterAddress;

		$preferenceLink = $this->renderLink(
			array(
				'label' => $this->msg( 'echo-email-html-footer-preference-link-text' )->text(),
				'url' => SpecialPage::getTitleFor( 'Preferences', false, 'mw-prefsection-echo' )->getFullURL( '', false, PROTO_CANONICAL ),
			),
			'text-decoration: none; color: #3868B0;'
		);

		$footer = $this->msg( 'echo-email-html-footer-with-link' )
			->rawParams( $preferenceLink )
			->parse();

		if ( $wgEchoEmailFooterAddress ) {
			$footer .= '<br />' . $wgEchoEmailFooterAddress;
		}

		return $footer;
	}

	private function renderLink( $link, $style ) {
		return Html::element(
			'a',
			array(
				'href' => wfExpandUrl( $link['url'], PROTO_CANONICAL ),
				'style' => $style,
			),
			$link['label']
		);
	}
}
