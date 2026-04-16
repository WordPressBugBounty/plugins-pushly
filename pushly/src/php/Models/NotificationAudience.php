<?php

namespace Pushly\Models;

class NotificationAudience implements \JsonSerializable {
	public ?bool $all_subscribers = null;
	public ?array $segment_ids = null;

	#[\ReturnTypeWillChange]
	public function jsonSerialize(): array {
		if ( $this->all_subscribers ) {
			return [ 'all_subscribers' => $this->all_subscribers ];
		}

		return [ 'segment_ids' => $this->segment_ids ];
	}
}
