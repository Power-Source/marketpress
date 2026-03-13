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
		background: #fff;
		border: 1px solid #ddd;
		overflow: hidden;
	}

	.header-bar {
		background: linear-gradient(135deg, <?php echo esc_attr( $vars['primary_color'] ); ?> 0%, <?php echo esc_attr( $vars['accent_color'] ); ?> 100%);
		color: #fff;
		padding: 14px 18px;
	}

	.header-flex {
		display: table;
		width: 100%;
	}

	.header-left,
	.header-right {
		display: table-cell;
		vertical-align: middle;
	}

	.header-left {
		width: 60%;
	}

	.header-right {
		width: 40%;
		text-align: right;
	}

	.header-bar img {
		max-height: 46px;
		width: auto;
		filter: brightness(0) invert(1);
	}

	.document-title {
		font-size: 18pt;
		font-weight: bold;
	}

	.document-meta {
		font-size: 8pt;
		opacity: 0.95;
	}

	.content {
		padding: 18px;
	}

	.sender {
		font-size: 7pt;
		color: #666;
		padding: 3px 0;
		margin-bottom: 12px;
		border-bottom: 1px solid #e5e5e5;
	}

	.meta {
		float: right;
		width: 42%;
		margin: 0 0 12px 12px;
		padding: 8px;
		background: #f6f9fc;
		border-left: 3px solid <?php echo esc_attr( $vars['primary_color'] ); ?>;
		font-size: 8pt;
	}

	.meta table {
		width: 100%;
	}

	.meta td {
		padding: 2px 4px;
	}

	.address-table {
		width: 100%;
		border-collapse: separate;
		border-spacing: 0;
		margin-bottom: 12px;
	}

	.address-table td {
		width: 50%;
		vertical-align: top;
		padding-right: 10px;
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
		margin-bottom: 14px;
	}

	table.items {
		width: 100%;
		border-collapse: collapse;
		font-size: 9pt;
	}

	table.items thead th {
		padding: 8px;
		background: <?php echo esc_attr( $vars['primary_color'] ); ?>;
		color: #fff;
		text-align: left;
		font-weight: normal;
	}

	table.items tbody td {
		padding: 6px 8px;
		border-bottom: 1px solid #eef2f5;
	}

	table.items tbody tr:nth-child(even) td {
		background: #f9fbfd;
	}

	table.items tbody td:last-child {
		text-align: center;
		width: 18%;
	}

	.note {
		margin-top: 14px;
		padding: 8px;
		font-size: 8pt;
		background: #fffef0;
		border-left: 3px solid <?php echo esc_attr( $vars['accent_color'] ); ?>;
	}

	.footer {
		margin-top: 16px;
		padding-top: 8px;
		border-top: 1px solid #e5e5e5;
		font-size: 7pt;
		color: #666;
		text-align: center;
	}

	<?php if ( ! empty( $vars['custom_css'] ) ): ?>
	<?php echo $vars['custom_css']; ?>
	<?php endif; ?>
</style>

<div class="pdf-wrapper">
	<div class="header-bar">
		<div class="header-flex">
			<div class="header-left">{{logo}}</div>
			<div class="header-right">
				<div class="document-title"><?php _e( 'Lieferschein', 'mp' ); ?></div>
				<div class="document-meta">#{{order_id}}</div>
			</div>
		</div>
	</div>

	<div class="content">
		<div class="sender">{{company_name}} &bull; {{company_address}}</div>

		<div class="meta">
			<table>
				<tr>
					<td><strong><?php _e( 'Bestellung', 'mp' ); ?>:</strong></td>
					<td style="text-align: right;">#{{order_id}}</td>
				</tr>
				<tr>
					<td><strong><?php _e( 'Datum', 'mp' ); ?>:</strong></td>
					<td style="text-align: right;"><?php echo date_i18n( 'd.m.Y', current_time( 'timestamp' ) ); ?></td>
				</tr>
			</table>
		</div>

		<table class="address-table">
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

		<div style="clear: both;"></div>

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

		<div class="note"><?php _e( 'Bitte Lieferung auf Vollstaendigkeit und Schaeden beim Empfang pruefen.', 'mp' ); ?></div>

		<div class="footer">{{company_name}} &bull; {{company_address}}</div>
	</div>
</div>