<?php

namespace App;

enum AdminActionEnum: string
{
    case AlertCancelled = 'alert.cancelled';
    case AlertsPurged = 'alerts.purged';
    case PushSent = 'push.sent';
}
