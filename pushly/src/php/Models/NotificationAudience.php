<?php

namespace Pushly\Models;

class NotificationAudience implements \JsonSerializable {
	public ?bool $all_subscribers = null;
	public ?array $segment_ids = null;
	public ?array $excluded_segment_ids = null;

	#[\ReturnTypeWillChange]
	public function jsonSerialize(): array {
		$audience = $this->all_subscribers
			? [ 'all_subscribers' => $this->all_subscribers ]
			: [ 'segment_ids' => $this->segment_ids ];

		// Exclusions accompany the audience rather than replacing it: the API
		// accepts excluded_segment_ids alongside any audience type, so an empty
		// targeting selection plus exclusions means "all subscribers except these".
		if ( ! empty( $this->excluded_segment_ids ) ) {
			$audience['excluded_segment_ids'] = $this->excluded_segment_ids;
		}

		return $audience;
	}
}
