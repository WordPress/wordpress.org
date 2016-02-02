<!doctype html>
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
	<!--[if gte mso 15]>
	<xml>
		<o:OfficeDocumentSettings>
			<o:AllowPNG />
			<o:PixelsPerInch>96</o:PixelsPerInch>
		</o:OfficeDocumentSettings>
	</xml>
	<![endif]-->
	<meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $phpmailer->Subject ); ?></title>

	<style type="text/css">
		p {
			margin: 10px 0;
			padding: 0;
		}

		table {
			border-collapse: collapse;
		}

		h1, h2, h3, h4, h5, h6 {
			display: block;
			margin: 0;
			padding: 0;
		}

		img, a img {
			border: 0;
			height: auto;
			outline: none;
			text-decoration: none;
		}

		body, #bodyTable, #bodyCell {
			height: 100%;
			margin: 0;
			padding: 0;
			width: 100%;
		}

		#outlook a {
			padding: 0;
		}

		img {
			-ms-interpolation-mode: bicubic;
		}

		table {
			mso-table-lspace: 0pt;
			mso-table-rspace: 0pt;
		}

		p, a, li, td, blockquote {
			mso-line-height-rule: exactly;
		}

		a[href^=tel], a[href^=sms] {
			color: inherit;
			cursor: default;
			text-decoration: none;
		}

		p, a, li, td, body, table, blockquote {
			-ms-text-size-adjust: 100%;
			-webkit-text-size-adjust: 100%;
		}

		a[x-apple-data-detectors] {
			color: inherit !important;
			text-decoration: none !important;
			font-size: inherit !important;
			font-family: inherit !important;
			font-weight: inherit !important;
			line-height: inherit !important;
		}

		#bodyCell {
			padding: 10px;
		}

		.templateContainer {
			max-width: 600px !important;
		}

		.mcnTextContent {
			word-break: break-word;
		}

		.mcnTextContent img {
			height: auto !important;
		}

		body, #bodyTable {
			background-color: #FAFAFA;
		}

		#bodyCell {
			border-top: 0;
		}

		.templateContainer {
			border: 0;
		}

		h1 {
			color: #202020;
			font-family: Helvetica;
			font-size: 26px;
			font-style: normal;
			font-weight: bold;
			line-height: 125%;
			letter-spacing: normal;
			text-align: left;
		}

		h2 {
			color: #202020;
			font-family: Helvetica;
			font-size: 22px;
			font-style: normal;
			font-weight: bold;
			line-height: 125%;
			letter-spacing: normal;
			text-align: left;
		}

		h3 {
			color: #202020;
			font-family: Helvetica;
			font-size: 20px;
			font-style: normal;
			font-weight: bold;
			line-height: 125%;
			letter-spacing: normal;
			text-align: left;
		}

		h4 {
			color: #202020;
			font-family: Helvetica;
			font-size: 18px;
			font-style: normal;
			font-weight: bold;
			line-height: 125%;
			letter-spacing: normal;
			text-align: left;
		}

		#templatePreheader .mcnTextContent, #templatePreheader .mcnTextContent p {
			color: #656565;
			font-family: Helvetica;
			font-size: 12px;
			line-height: 150%;
			text-align: left;
		}

		#templatePreheader .mcnTextContent a, #templatePreheader .mcnTextContent p a {
			color: #656565;
			font-weight: normal;
			text-decoration: underline;
		}

		#templateHeader {
			background-color: #FFFFFF;
			border-top: 0;
			border-bottom: 0;
			padding-top: 9px;
			padding-bottom: 0;
		}

		#templateHeader .mcnTextContent, #templateHeader .mcnTextContent p {
			color: #202020;
			font-family: Helvetica;
			font-size: 16px;
			line-height: 150%;
			text-align: left;
		}

		#templateHeader .mcnTextContent a, #templateHeader .mcnTextContent p a {
			color: #2BAADF;
			font-weight: normal;
			text-decoration: underline;
		}

		#templateBody {
			background-color: #FFFFFF;
			border-top: 0;
			border-bottom: 2px solid #EAEAEA;
			padding-top: 0;
			padding-bottom: 9px;
		}

		#templateBody .mcnTextContent, #templateBody .mcnTextContent p {
			color: #202020;
			font-family: Helvetica;
			font-size: 16px;
			line-height: 150%;
			text-align: left;
		}

		#templateBody .mcnTextContent a, #templateBody .mcnTextContent p a {
			color: #2BAADF;
			font-weight: normal;
			text-decoration: underline;
		}

		#templateFooter {
			background-color: #FAFAFA;
			border-top: 0;
			border-bottom: 0;
			padding-top: 9px;
			padding-bottom: 9px;
		}

		#templateFooter .mcnTextContent, #templateFooter .mcnTextContent p {
			color: #656565;
			font-family: Helvetica;
			font-size: 12px;
			line-height: 150%;
			text-align: center;
		}

		#templateFooter .mcnTextContent a, #templateFooter .mcnTextContent p a {
			color: #656565;
			font-weight: normal;
			text-decoration: underline;
		}

		@media only screen and (min-width: 768px) {
			.templateContainer {
				width: 600px !important;
			}
		}

		@media only screen and (max-width: 480px) {
			body, table, td, p, a, li, blockquote {
				-webkit-text-size-adjust: none !important;
			}
		}

		@media only screen and (max-width: 480px) {
			body {
				width: 100% !important;
				min-width: 100% !important;
			}
		}

		@media only screen and (max-width: 480px) {
			#bodyCell {
				padding-top: 10px !important;
			}

		}

		@media only screen and (max-width: 480px) {
			.mcnTextContentContainer {
				max-width: 100% !important;
				width: 100% !important;
			}
		}

		@media only screen and (max-width: 480px) {
			.mcnCaptionLeftContentOuter .mcnTextContent, .mcnCaptionRightContentOuter .mcnTextContent {
				padding-top: 9px !important;
			}
		}

		@media only screen and (max-width: 480px) {
			.mcnCaptionBlockInner .mcnCaptionTopContent:last-child .mcnTextContent {
				padding-top: 18px !important;
			}
		}

		@media only screen and (max-width: 480px) {
			.mcnTextContent {
				padding-right: 18px !important;
				padding-left: 18px !important;
			}
		}

		@media only screen and (max-width: 480px) {
			h1 {
				font-size: 22px !important;
				line-height: 125% !important;
			}
		}

		@media only screen and (max-width: 480px) {
			h2 {
				font-size: 20px !important;
				line-height: 125% !important;
			}
		}

		@media only screen and (max-width: 480px) {
			h3 {
				font-size: 18px !important;
				line-height: 125% !important;
			}
		}

		@media only screen and (max-width: 480px) {
			h4 {
				font-size: 16px !important;
				line-height: 150% !important;
			}
		}

		@media only screen and (max-width: 480px) {
			.mcnBoxedTextContentContainer .mcnTextContent, .mcnBoxedTextContentContainer .mcnTextContent p {
				font-size: 14px !important;
				line-height: 150% !important;
			}
		}

		@media only screen and (max-width: 480px) {
			#templatePreheader .mcnTextContent, #templatePreheader .mcnTextContent p {
				font-size: 14px !important;
				line-height: 150% !important;
			}

		}

		@media only screen and (max-width: 480px) {
			#templateHeader .mcnTextContent, #templateHeader .mcnTextContent p {
				font-size: 16px !important;
				line-height: 150% !important;
			}
		}

		@media only screen and (max-width: 480px) {
			#templateBody .mcnTextContent, #templateBody .mcnTextContent p {
				font-size: 16px !important;
				line-height: 150% !important;
			}
		}

		@media only screen and (max-width: 480px) {
			#templateFooter .mcnTextContent, #templateFooter .mcnTextContent p {
				font-size: 14px !important;
				line-height: 150% !important;
			}
		}
	</style>
</head>

<body style="height: 100%;margin: 0;padding: 0;width: 100%;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;background-color: #FAFAFA;">
	<center>
		<table align="center" border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" id="bodyTable" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;height: 100%;margin: 0;padding: 0;width: 100%;background-color: #FAFAFA;">
			<tr>
				<td align="center" valign="top" id="bodyCell" style="mso-line-height-rule: exactly;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;height: 100%;margin: 0;padding: 10px;width: 100%;border-top: 0;">
					<!--[if gte mso 9]>
					<table align="center" border="0" cellspacing="0" cellpadding="0" width="600" style="width:600px;">
						<tr>
							<td align="center" valign="top" width="600" style="width:600px;">
					<![endif]-->

					<table border="0" cellpadding="0" cellspacing="0" width="100%" class="templateContainer" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;border: 0;max-width: 600px !important;">
						<tr>
							<td valign="top" id="templateHeader" style="mso-line-height-rule: exactly;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;background-color: #FFFFFF;border-top: 0;border-bottom: 0;padding-top: 0px;padding-bottom: 0;">
								<table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnTextBlock" style="min-width: 100%;border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;">
									<tbody class="mcnTextBlockOuter">
										<tr>
											<td valign="top" class="mcnTextBlockInner" style="mso-line-height-rule: exactly;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;">
												<table align="left" border="0" cellpadding="0" cellspacing="0" width="100%" style="min-width: 100%;border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;" class="mcnTextContentContainer">
													<tbody>
														<tr>
															<td valign="top" class="mcnTextContent" style="padding: 9px 18px;color: #0B5F7B; background-color: #d3e7f8; mso-line-height-rule: exactly;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;word-break: break-word;font-family: Helvetica;font-size: 16px;line-height: 150%;text-align: center;">

																<div style="text-align: center;">
																	<img
																		align="center"
																		width="100"
																		height="100"
																		src="<?php echo esc_url( $logo_url ); ?>"
																		alt="WordPress"
																		style="width: 100px;height: 100px;margin: 0px;border: 0;outline: none;text-decoration: none;-ms-interpolation-mode: bicubic;">
																</div>

																<h1 class="null" style="text-align: center;display: block;margin: 5px 0 0 0;padding: 0;color: #202020;font-family:verdana,geneva,sans-serif;font-size:38px;font-style: normal;font-weight: bold;line-height: 125%;letter-spacing: normal;">
																	<?php echo esc_html( get_wordcamp_name() ); ?>
																</h1>

															</td>
														</tr>
													</tbody>
												</table>
											</td>
										</tr>
									</tbody>
								</table>
							</td>
						</tr>

						<tr>
							<td valign="top" id="templateBody" style="mso-line-height-rule: exactly;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;background-color: #FFFFFF;border-top: 0;border-bottom: 2px solid #EAEAEA;padding-top: 0;padding-bottom: 9px;">
								<table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnTextBlock" style="min-width: 100%;border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;">
									<tbody class="mcnTextBlockOuter">
										<tr>
											<td valign="top" class="mcnTextBlockInner" style="mso-line-height-rule: exactly;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;">
												<table align="left" border="0" cellpadding="0" cellspacing="0" width="100%" style="min-width: 100%;border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;" class="mcnTextContentContainer">
													<tbody>
														<tr>
															<td valign="top" class="mcnTextContent" style="padding: 9px 18px;color: #545454;mso-line-height-rule: exactly;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;word-break: break-word;font-family: Helvetica;font-size: 16px;line-height: 150%;text-align: left;">
