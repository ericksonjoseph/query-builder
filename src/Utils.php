<?php

class Utils {

    const DATE_FORMAT_SQL 	= 'Y-m-d';

    /**
     * Formats a date according to the type for use in range calculations
     * Adds 23hours 59mins & 59secs to the end date to make sure we get results for the full day requested
     *
     * @param mixed $value
     * @param mixed $format
     * @param mixed $type
     * @static
     * @access public
     * @return void
     */
    public static function formatDateRange($value, $format = self::DATE_FORMAT_SQL, $type) {

        switch ($type) {
            case 'start':
                $formatted = self::formatDate($value, $format);
                break;
            case 'end':
                $date = self::createDateObject($value);
                $interval = new DateInterval('PT23H59M59S');
                $date->add($interval);
                $formatted = self::formatDate($date, $format);
                break;
            default:
                throw 'Cannot Format Date of type ' . $type;
                break;
        }

        return $formatted;
    }

    /**
     * Returns the specified value formatted as a date-time based
     * on desired format.
     * 
     * @param mixed $value
     * @param string $format
     * @return string
     */
    public static function formatDate($value, $format = self::DATE_FORMAT_SQL) 
    {
        if (!($value instanceof DateTime)) {
            if (!intval($value)) {
                throw new \Exception("This is not a valid date $value");
                return false;
            }

            $value = self::createDateObject($value);
        }

        return $value->format($format);
    }

    /**
     * Convert a date string or timestamp into a DateTime object
     *
     * @param mixed $value
     * @static
     * @access public
     * @return void
     */
    public static function createDateObject($value)
    {
        if ($value instanceof DateTime) {
            $date = $value;
        } 
        elseif (is_string($value) && strlen($value) ) {
            $date = new DateTime($value);
        } 
        elseif ( is_numeric($value) ) {
            $date = new DateTime();
            $date->setTimestamp($value);
        } 
        else {
            return '';
        }

        return $date;
    }
}
