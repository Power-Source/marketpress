<title><?php _e( 'Lieferschein', 'mp' ); ?> #{{order_id}}</title>
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
		color: #111;
	}

	.pdf-wrapper {
		padding: 22px;
		border: 2px solid #111;
		background: #fff;
	}

	.header {
		margin-bottom: 14px;
		padding-bottom: 10px;
		border-bottom: 2px solid #111;
	}

	.header-table {
		display: table;
		width: 100%;
	}

	.header-left,
	.header-right {
		display: table-cell;
		vertical-align: top;
	}

	.header-right {
		text-align: right;
	}

	.header img {
		max-height: 50px;
		width: auto;
	}

	.document-title {
		font-size: 20pt;
		font-weight: bold;
		letter-spacing: 1px;
	}

	.document-meta {
		font-size: 8pt;
		margin-top: 4px;
	}

	.sender {
		font-size: 7pt;
		margin-bottom: 10px;
		padding-bottom: 4px;
		border-bottom: 1px solid #333;
	}

	.details {
		width: 100%;
		margin: 10px 0 14px 0;
		border-collapse: separate;
		border-spacing: 0;
	}

	.details td {
		width: 50%;
		vertical-align: top;
		padding-right: 12px;
	}

	.address-box {
		padding: 8px;
		border: 1px solid #333;
		min-height: 70px;
	}

	.address-label {
		font-size: 8pt;
		font-weight: bold;
		margin-bottom: 4px;
	}

	.contact {
		font-size: 8pt;
		margin-bottom: 12px;
	}

	table.items {
		width: 100%;
		border-collapse: collapse;
		font-size: 9pt;
	}

	table.items thead th {
		padding: 7px 6px;
		border-top: 2px solid #111;
		border-bottom: 2px solid #111;
		text-align: left;
		font-weight: bold;
	}

	table.items tbody td {
		padding: 6px;
		border-bottom: 1px solid #ddd;
	}

	table.items tbody td:last-child {
		text-align: center;
		width: 18%;
	}

	.note {
		margin-top: 12px;
		padding: 8px;
		font-size: 8pt;
		border: 1px solid #333;
	}

	.footer {
		margin-top: 16px;
		padding-top: 8px;
		border-top: 1px solid #333;
		font-size: 7pt;
		text-align: center;
	}

	<?php if ( ! empty( $vars['custom_css'] ) ): ?>
	<?php echo $vars['custom_css']; ?>
	<?php endif; ?>
</style>

<div class="pdf-wrapper">
	<div class="header">
		<div class="header-table">
			<div class="header-left">{{logo}}</div>
			<div class="header-right">
				<div class="document-title"><?php _e( 'Lieferschein', 'mp' ); ?></div>
				<div class="document-meta">#{{order_id}}<br><?php echo date_i18n( 'd.m.Y', current_time( 'timestamp' ) ); ?></div>
			</div>
		</div>
	</div>

	<div class="sender">{{company_name}} &bull; {{company_address}}</div>

	<table class="details">
		<tr>
			<td>
				<div class="address-box">
					<div class="address-label"><?php _e( 'Rechnungsadresse', 'mp' ); ?></div>
					{{billing}}
				</div>
			</td>
			<?php if ( $show_shipping == true ): ?>
				<td style="padding-right: 0;">
					<div class="address-box">
						<div class="address-label"><?php _e( 'Lieferadresse', 'mp' ); ?></div>
						{{shipping}}
					</div>
				</td>
			<?php endif; ?>
		</tr>
	</table>

	<?php if ( ! empty( $email ) ): ?>
		<div class="contact">{{email}}</div>
	<?php endif; ?>

	<table class="items">
		<thead>
			<tr>
				<th><?php _e( 'Produktname', 'mp' ); ?></th>
				<th><?php _e( 'Menge', 'mp' ); ?></th>
			</tr>
		</thead>
		<tbody>
			{{order_details}}
		</tbody>
	</table>

	<div class="note"><?php _e( 'Interner Hinweis: Dieser Lieferschein enthaelt keine Preisangaben.', 'mp' ); ?></div>

	<div class="footer">{{company_name}} &bull; {{company_address}}</div>
</div>