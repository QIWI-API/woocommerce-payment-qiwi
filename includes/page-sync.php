<div class="wrap" id="page-sync">
	<h1>
		<?php
		/*
		 * translators:
		 * ru_RU: Синхронизация счетов QIWI Касса
		 */
		_e( 'Sync QIWI cash bills', 'woocommerce_payment_qiwi' );
		?>
	</h1>
	<p>
		<button class="button button-primary">
			<?php
			/*
			 * translators:
			 * ru_RU: Синхронизировать сейчас
			 */
			_e( 'Sync now', 'woocommerce_payment_qiwi' );
			?>
		</button>
		<progress class="hidden">
	</p>
	<output>
		<?php
		/*
		 * translators:
		 * ru_RU: Нажмите кнопку, что бы обновить статусы неоплаченных счетов. Это запустит процесс синхронизации статусов в API QIWI.
		 */
		_e( 'Click the button to update the statuses of unpaid invoices. This will start the process of synchronizing statuses in the QIWI API.', 'woocommerce_payment_qiwi' );
		?>
	</output>
</div>
