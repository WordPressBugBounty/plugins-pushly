<?php

namespace Pushly\Models;

class NotificationTemplateChannels implements \JsonSerializable {
	public ?NotificationTemplateChannelsWeb $web = null;

	#[\ReturnTypeWillChange]
	public function jsonSerialize(): array {
		return [
			// default mirrors web to ensure all values display correctly in the platform
			// TODO: enable multi-channel support in a future version
			'default' => $this->web,
			'web'     => $this->web,
		];
	}
}
