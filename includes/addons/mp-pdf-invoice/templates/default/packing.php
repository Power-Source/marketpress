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
		color: <?php echo esc_attr( $vars['text_color'] ); ?>;
	}

	.pdf-wrapper {
		padding: 20px;
		border: 1px solid #ddd;
		background: #fff;
	}

	.header {
		margin-bottom: 16px;
		padding-bottom: 10px;
		border-bottom: 2px solid <?php echo esc_attr( $vars['primary_color'] ); ?>;
	}

	.header-flex {
		display: table;
		width: 100%;
	}

	.header-left,
	.header-right {
		display: table-cell;
		vertical-align: top;
	}

	.header-left {
		width: 60%;
	}

	.header-right {
		width: 40%;
		text-align: right;
	}

	.header img {
		max-height: 52px;
		width: auto;
	}

	.document-title {
		font-size: 18pt;
		font-weight: bold;
		color: <?php echo esc_attr( $vars['primary_color'] ); ?>;
	}

	.document-meta {
		margin-top: 4px;
		font-size: 8pt;
		color: #666;
	}

	.company-line {
		font-size: 7pt;
		color: #666;
		padding: 3px 0;
		margin-bottom: 12px;
		border-bottom: 1px solid #e5e5e5;
	}

	.addresses {
		display: table;
		width: 100%;
		margin: 0 0 12px 0;
	}

	.addresses-col {
		display: table-cell;
		width: 50%;
		vertical-align: top;
		padding-right: 12px;
	}

	.address-box {
		padding: 9px;
		background: #fafafa;
		border-left: 3px solid <?php echo esc_attr( $vars['accent_color'] ); ?>;
		min-height: 70px;
	}

	.address-label {
		font-size: 8pt;
		font-weight: bold;
		margin-bottom: 4px;
		color: <?php echo esc_attr( $vars['primary_color'] ); ?>;
	}

	.contact {
		font-size: 8pt;
		margin: 0 0 14px 0;
	}

	table.items {
		width: 100%;
		border-collapse: collapse;
		font-size: 9pt;
	}

	table.items thead th {
		padding: 7px 8px;
		background: <?php echo esc_attr( $vars['primary_color'] ); ?>;
		color: #fff;
		text-align: left;
		font-weight: normal;
	}

	table.items tbody td {
		padding: 6px 8px;
		border-bottom: 1px solid #eee;
	}

	table.items tbody tr:nth-child(even) td {
		background: #fafafa;
	}

	table.items tbody td:last-child {
		text-align: center;
		width: 18%;
	}

	.note {
		margin-top: 14px;
		padding: 8px;
		font-size: 8pt;
		background: #fffbe6;
		border-left: 3px solid <?php echo esc_attr( $vars['accent_color'] ); ?>;
	}

	.footer {
		margin-top: 16px;
		padding-top: 8px;
		border-top: 1px solid #ddd;
		font-size: 7pt;
		color: #666;
		text-align: center;
	}

	<?php if ( ! empty( $vars['custom_css'] ) ): ?>
	<?php echo $vars['custom_css']; ?>
	<?php endif; ?>
</style>

<div class="pdf-wrapper">
	<div class="header">
		<div class="header-flex">
			<div class="header-left">
				{{logo}}
			</div>
			<div class="header-right">
				<div class="document-title"><?php _e( 'Lieferschein', 'mp' ); ?></div>
				<div class="document-meta">
					<?php _e( 'Bestellung', 'mp' ); ?> #{{order_id}}<br>
					<?php echo date_i18n( 'd.m.Y', current_time( 'timestamp' ) ); ?>
				</div>
			</div>
		</div>
	</div>

	<div class="company-line">{{company_name}} &bull; {{company_address}}</div>

	<div class="addresses">
		<div class="addresses-col">
			<div class="address-box">
				<div class="address-label"><?php _e( 'Rechnungsadresse', 'mp' ); ?></div>
				{{billing}}
			</div>
		</div>
		<?php if ( $show_shipping == true ): ?>
			<div class="addresses-col" style="padding-right: 0;">
				<div class="address-box">
					<div class="address-label"><?php _e( 'Lieferadresse', 'mp' ); ?></div>
					{{shipping}}
				</div>
			</div>
		<?php endif; ?>
	</div>

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

	<div class="note"><?php _e( 'Dieser Lieferschein dient als Beleg für die enthaltene Warenlieferung.', 'mp' ); ?></div>

	<div class="footer">{{company_name}} &bull; {{company_address}}</div>
</div>