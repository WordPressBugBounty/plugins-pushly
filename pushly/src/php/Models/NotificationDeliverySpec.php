<?php

namespace Pushly\Models;

class NotificationDeliverySpec implements \JsonSerializable {
	public ?string $type = null;
	public ?string $window = null;
	public ?string $send_date_utc = null;

	#[\ReturnTypeWillChange]
	public function jsonSerialize(): array {
		$r = [
			'type'   => $this->type,
			'window' => $this->window,
		];

		if ( $this->type === 'SCHEDULED' ) {
			$r['send_date_utc'] = $this->send_date_utc;
		}

		return $r;
	}
}
