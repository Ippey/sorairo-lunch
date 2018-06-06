<?php
/**
 * Created by PhpStorm.
 * User: ippei
 * Date: 2018/06/06
 * Time: 11:49
 */

namespace App\Service;

use Cake\Core\Configure;
use Carbon\Carbon;

class ShopService extends AppService
{
    /** @var array */
    private $openDate;

    public function __construct($name = null)
    {
        $this->openDate = Configure::read('openDate');
        parent::__construct($name);
    }

    /**
     * @param Carbon|null $today
     * @return bool
     */
    public function isOrderAvailable(Carbon $today = null)
    {
        if (is_null($today)) {
            $today = Carbon::today();
        }
        $tomorrow = $today->addDays(1);
        $isOrderAvailable = false;
        $strToday = $today->format('Y-m-d');
        $strTomorrow = $tomorrow->format('Y-m-d');
        if (array_key_exists($strToday, $this->openDate) && 1 == $this->openDate[$strToday]) {
            $isOrderAvailable = true;
        } else if (array_key_exists($strTomorrow, $this->openDate) && 1 == $this->openDate[$strTomorrow]) {
            $isOrderAvailable = true;
        }
        return $isOrderAvailable;
    }
}
