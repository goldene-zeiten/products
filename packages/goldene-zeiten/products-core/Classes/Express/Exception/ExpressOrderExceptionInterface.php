<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Express\Exception;

/**
 * Marker for every way an express order can fail to be placed. An express confirm endpoint catches it to
 * answer with a handled error instead of a 500 - by the time it is thrown, any money that had been taken
 * has been given back.
 */
interface ExpressOrderExceptionInterface extends \Throwable {}
