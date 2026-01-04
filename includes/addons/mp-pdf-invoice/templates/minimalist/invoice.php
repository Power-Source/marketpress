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
		line-height: 1.4;
		color: #000;
	}
	
	.pdf-wrapper {
		padding: 25px;
		border: 2px solid #000;
		background: #fff;
	}

	.header {
		padding-bottom: 15px;
		border-bottom: 3px solid #000;
		margin-bottom: 20px;
	}
	
	.header-content {
		display: table;
		width: 100%;
	}
	
	.header-left {
		display: table-cell;
		width: 50%;
		vertical-align: top;
	}
	
	.header-right {
		display: table-cell;
		width: 50%;
		vertical-align: top;
		text-align: right;
	}
	
	.header img {
		max-height: 50px;
		width: auto;
	}
	
	.company-details {
		font-size: 8pt;
		line-height: 1.3;
	}
	
	.invoice-title {
		font-size: 24pt;
		font-weight: bold;
		letter-spacing: 2px;
		margin: 0;
	}
	
	.invoice-meta {
		font-size: 9pt;
		margin-top: 5px;
	}

	.sender-line {
		font-size: 7pt;
		padding: 2px 0;
		margin-bottom: 5px;
		border-bottom: 1px solid #000;
	}
	
	.recipient {
		margin: 10px 0 20px 0;
		min-height: 60px;
	}
	
	.details-table {
		width: 100%;
		margin: 15px 0;
		border-top: 1px solid #000;
		border-bottom: 1px solid #000;
		padding: 10px 0;
		font-size: 8pt;
	}
	
	.details-table table {
		width: 100%;
	}
	
	.details-table td {
		padding: 3px 0;
	}

	table.items {
		width: 100%;
		border-collapse: collapse;
		margin: 15px 0;
		font-size: 9pt;
	}

	table.items thead th {
		border-bottom: 2px solid #000;
		padding: 8px 5px;
		text-align: left;
		font-weight: bold;
	}

	table.items tbody td {
		padding: 6px 5px;
		border-bottom: 1px solid #ddd;
	}
	
	table.items tbody tr.subtotal td {
		border-top: 1px solid #000;
		padding-top: 10px;
	}
	
	table.items tbody tr.total td {
		border-top: 2px solid #000;
		border-bottom: 2px solid #000;
		font-weight: bold;
		font-size: 11pt;
		padding: 10px 5px;
	}
	
	table.items tbody td.no-border {
		border-bottom: none;
	}
	
	.shipping-info {
		margin: 15px 0;
		padding: 10px 0;
		border-top: 1px solid #ddd;
		border-bottom: 1px solid #ddd;
		font-size: 8pt;
	}
	
	.legal-text {
		margin: 15px 0;
		padding: 10px;
		border: 1px solid #ddd;
		font-size: 8pt;
		background: #fafafa;
	}
	
	.footer {
		margin-top: 25px;
		padding-top: 10px;
		border-top: 1px solid #000;
		font-size: 7pt;
		text-align: center;
	}
	
	<?php if (!empty($vars['custom_css'])): ?>
	/* Custom CSS */
	<?php echo $vars['custom_css']; ?>
	<?php endif; ?>
</style>

<div class="pdf-wrapper">
<div class="container">
	<!-- Header -->
	<div class="header">
		<div class="header-content">
			<div class="header-left">
				{{logo}}
				<div class="company-details" style="margin-top: 10px;">
					<strong>{{company_name}}</strong><br>
					{{company_address}}
				</div>
			</div>
			<div class="header-right">
				<div class="invoice-title">RECHNUNG</div>
				<div class="invoice-meta">
					Nr. {{invoice_number}}<br>
					vom <?php echo date_i18n( 'd.m.Y', current_time( 'timestamp' ) ); ?>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Absender -->
	<div class="sender-line">
		{{company_name}} &bull; {{company_address}}
	</div>
	
	<!-- Empfänger -->
	<div class="recipient">
		{{billing}}
	</div>
	
	<!-- Details Box -->
	<div class="details-table">
		<table>
			<tr>
				<td style="width: 30%;"><strong>Rechnungsnummer:</strong></td>
				<td>{{invoice_number}}</td>
				<td style="width: 30%; text-align: right;"><strong>Rechnungsdatum:</strong></td>
				<td style="text-align: right;"><?php echo date_i18n( 'd.m.Y', current_time( 'timestamp' ) ); ?></td>
			</tr>
			<tr>
				<td><strong>Bestellnummer:</strong></td>
				<td colspan="3">{{order_id}}</td>
			</tr>
		</table>
	</div>
	
	<!-- Artikeltabelle -->
	<table class="items">
		<thead>
			<tr>
				<th style="width: 50%;">Artikel</th>
				<th style="width: 10%; text-align: center;">Menge</th>
				<th style="width: 20%; text-align: right;">Einzelpreis</th>
				<th style="width: 20%; text-align: right;">Gesamtpreis</th>
			</tr>
		</thead>
		<tbody>
			{{order_details}}
		</tbody>
	</table>
	
	<!-- Versandadresse -->
	<?php if ( $show_shipping == true ): ?>
	<div class="shipping-info">
		<strong>Versandadresse:</strong><br>
		<div style="margin-top: 5px;">{{shipping}}</div>
	</div>
	<?php endif; ?>
	
	<!-- Rechtlicher Hinweis -->
	<?php if ( !empty($vars['custom_note']) ): ?>
	<div class="legal-text">
		{{custom_note}}
	</div>
	<?php endif; ?>
	
	<!-- Footer -->
	<div class="footer">
		{{company_name}} &bull; {{company_address}}<br>
		<?php if ( !empty($vars['vat_id']) || !empty($vars['tax_number']) ): ?>
			<?php if ( !empty($vars['vat_id']) ): ?>USt-IdNr.: {{vat_id}}<?php endif; ?>
			<?php if ( !empty($vars['vat_id']) && !empty($vars['tax_number']) ): ?> &bull; <?php endif; ?>
			<?php if ( !empty($vars['tax_number']) ): ?>Steuernummer: {{tax_number}}<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
</div>
