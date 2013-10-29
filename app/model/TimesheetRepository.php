<?php
use Nette\Database\SqlLiteral;

/**
 * Projet: faktury
 * Author: Matej Sajgal
 * Date: 27.10.2013
 */

class TimesheetRepository extends Repository
{
    public function insertTimeRow($data)
    {
        return $this->getTable()->insert($data);
    }

    public function updateTimeRow($rowId, $userId, $data)
    {
        $this->findBy(array('id' => $rowId, 'user_id' => $userId))->update($data);
    }

    public function isMineAndTodayTimesheet($timesheetId, $userId)
    {
        $timesheet = $this->fetchById($timesheetId);

        if ($timesheet !== FALSE && $timesheet->user_id == $userId && $timesheet->from->format('d.m.Y') == date('d.m.Y') ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $user_id
     *
     * @return array|IRow[]
     */
    public function getTodaysTimesheets($user_id)
    {
        return $this->getTable()
            ->where('user_id', $user_id)
            ->where('? = ?', new SqlLiteral('DATE_FORMAT(NOW(), "%Y-%m-%d")'), new SqlLiteral('DATE_FORMAT(`from`,"%Y-%m-%d")'))
            ->order('from')
            ->fetchAll();

    }

    public function deleteTimeSheet($timesheetId, $userId)
    {
        $this->findBy(array('user_id' => $userId, 'id' => $timesheetId))->delete();
    }

    public function getTodayWorkHours($user_id)
    {
        $sumHours = 0;
        $sumMinutes = 0;
        $out = array();

        $timesheets = $this->getTable()
            ->where('? = ?', new SqlLiteral('DATE_FORMAT(NOW(), "%Y-%m-%d")'), new SqlLiteral('DATE_FORMAT(`from`,"%Y-%m-%d")'))
            ->where('user_id', $user_id)
            ->fetchAll();

        if(!$timesheets) {
            return false;
        }

        foreach($timesheets as $timesheet) {
            $diff = $timesheet->to->diff($timesheet->from);
            $sumHours += (int)$diff->format('%h');
            $sumMinutes += (int)$diff->format('%i');
        }

        $restHours = $sumMinutes/60;
        $out['hours'] = (int)$restHours + (int)$sumHours;
        $out['minutes'] = (int)$sumMinutes%60;

        return $out;
    }
}