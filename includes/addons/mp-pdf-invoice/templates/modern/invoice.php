<title>Rechnung #{{invoice_number}}</title>
<style type="text/css">
	* {
		margin: 0;
		padding: 0;
		box-sizing: border-box;
	}
	
	@page {
		margin: 1cm;
	}
	
	body {
		font-family: 'DejaVu Sans', Arial, sans-serif;
		font-size: 9pt;
		line-height: 1.3;
		color: <?php echo esc_attr($vars['text_color']); ?>;
	}
	
	.pdf-wrapper {
		padding: 0;
		border: 1px solid #ddd;
		background: #fff;
		overflow: hidden;
	}

	.header-bar {
		background: linear-gradient(135deg, <?php echo esc_attr($vars['primary_color']); ?> 0%, <?php echo esc_attr($vars['accent_color']); ?> 100%);
		padding: 15px 20px;
		margin: 0 0 15px 0;
		color: #fff;
	}
	
	.header-content {
		display: table;
		width: 100%;
	}
	
	.header-left {
		display: table-cell;
		width: 60%;
		vertical-align: middle;
	}
	
	.header-right {
		display: table-cell;
		width: 40%;
		vertical-align: middle;
		text-align: right;
	}
	
	.header-bar img {
		max-height: 45px;
		width: auto;
		filter: brightness(0) invert(1);
	}
	
	.invoice-number {
		font-size: 20pt;
		font-weight: bold;
		margin: 0;
	}
	
	.invoice-date {
		font-size: 8pt;
		opacity: 0.9;
	}

	.sender-info {
		font-size: 7pt;
		color: #666;
		padding: 3px 0;
		margin-bottom: 8px;
		border-bottom: 1px solid #eee;
	}
	
	.recipient {
		margin: 10px 0 15px 0;
		line-height: 1.4;
	}
	
	.meta-box {
		float: right;
		width: 40%;
		margin: 0 0 15px 15px;
		padding: 10px;
		background: <?php echo esc_attr($vars['primary_color']); ?>;
		color: #fff;
		font-size: 8pt;
	}
	
	.meta-box table {
		width: 100%;
	}
	
	.meta-box td {
		padding: 3px 5px;
	}

	.greeting {
		margin: 15px 0 10px 0;
		font-size: 8pt;
		line-height: 1.5;
	}

	table.items {
		width: 100%;
		border-collapse: collapse;
		margin: 10px 0;
		font-size: 9pt;
	}

	table.items thead th {
		background: <?php echo esc_attr($vars['primary_color']); ?>;
		padding: 8px;
		color: #fff;
		text-align: left;
		font-weight: normal;
	}

	table.items tbody td {
		padding: 6px 8px;
		border-bottom: 1px solid #f0f0f0;
	}
	
	table.items tbody tr:nth-child(even) {
		background: #fafafa;
	}
	
	table.items tbody tr.subtotal td {
		border-top: 2px solid #ddd;
		font-weight: bold;
		padding-top: 8px;
	}
	
	table.items tbody tr.total td {
		border-top: 3px solid <?php echo esc_attr($vars['primary_color']); ?>;
		background: <?php echo esc_attr($vars['accent_color']); ?>;
		color: #fff;
		font-weight: bold;
		font-size: 10pt;
		padding: 10px 8px;
	}
	
	table.items tbody td.no-border {
		border-bottom: none;
	}
	
	.shipping-box {
		margin: 10px 0;
		padding: 10px;
		background: #f5f5f5;
		border-left: 4px solid <?php echo esc_attr($vars['accent_color']); ?>;
		font-size: 8pt;
	}
	
	.legal-notice {
		margin: 10px 0;
		padding: 10px;
		background: #fffef0;
		border-left: 4px solid <?php echo esc_attr($vars['accent_color']); ?>;
		font-size: 8pt;
	}
	
	.footer {
		margin-top: 20px;
		padding-top: 10px;
		border-top: 2px solid <?php echo esc_attr($vars['primary_color']); ?>;
		font-size: 7pt;
		color: #666;
		text-align: center;
	}
	
	<?php if (!empty($vars['custom_css'])): ?>
	/* Custom CSS */
	<?php echo $vars['custom_css']; ?>
	<?php endif; ?>
</style>

<div class="pdf-wrapper">
<!-- Farbiger Header -->
<div class="header-bar">
	<div class="header-content">
		<div class="header-left">
			{{logo}}
		</div>
		<div class="header-right">
			<div class="invoice-number">Rechnung</div>
			<div class="invoice-date">#{{invoice_number}}</div>
		</div>
	</div>
</div>

<div class="container" style="padding: 0 20px 20px 20px;">
	<!-- Meta-Informationen Box -->
	<div class="meta-box">
		<table>
			<tr>
				<td><strong>Rechnungsnummer:</strong></td>
				<td style="text-align: right;">{{invoice_number}}</td>
			</tr>
			<tr>
				<td><strong>Datum:</strong></td>
				<td style="text-align: right;"><?php echo date_i18n( 'd.m.Y', current_time( 'timestamp' ) ); ?></td>
			</tr>
			<tr>
				<td><strong>Bestellung:</strong></td>
				<td style="text-align: right;">{{order_id}}</td>
			</tr>
		</table>
	</div>
	
	<!-- Absender -->
	<div class="sender-info">
		{{company_name}} &bull; {{company_address}}
	</div>
	
	<!-- Empfänger -->
	<div class="recipient">
		{{billing}}
	</div>
	
	<div style="clear: both;"></div>
	
	<div class="greeting">
		<strong>Sehr geehrte Damen und Herren,</strong><br>
		vielen Dank für Ihre Bestellung. Nachfolgend finden Sie die Rechnung für folgende Position(en):
	</div>
	
	<!-- Artikeltabelle -->
	<table class="items">
		<thead>
			<tr>
				<th style="width: 48%;">Artikel</th>
				<th style="width: 12%; text-align: center;">Menge</th>
				<th style="width: 20%; text-align: right;">Einzelpreis</th>
				<th style="width: 20%; text-align: right;">Gesamt</th>
			</tr>
		</thead>
		<tbody>
			{{order_details}}
		</tbody>
	</table>
	
	<!-- Versandadresse -->
	<?php if ( $show_shipping == true ): ?>
	<div class="shipping-box">
		<strong>Versandadresse:</strong><br>
		<div style="margin-top: 5px;">{{shipping}}</div>
	</div>
	<?php endif; ?>
	
	<!-- Rechtlicher Hinweis -->
	<?php if ( !empty($vars['custom_note']) ): ?>
	<div class="legal-notice">
		<strong>Hinweis:</strong> {{custom_note}}
	</div>
	<?php endif; ?>
	
	<!-- Footer -->
	<div class="footer">
		<strong>{{company_name}}</strong> &bull; {{company_address}}<br>
		<?php if ( !empty($vars['vat_id']) || !empty($vars['tax_number']) ): ?>
			<?php if ( !empty($vars['vat_id']) ): ?>USt-IdNr.: {{vat_id}}<?php endif; ?>
			<?php if ( !empty($vars['vat_id']) && !empty($vars['tax_number']) ): ?> &bull; <?php endif; ?>
			<?php if ( !empty($vars['tax_number']) ): ?>Steuernummer: {{tax_number}}<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
</div>
