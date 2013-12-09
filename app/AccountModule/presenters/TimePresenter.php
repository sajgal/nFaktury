<?php
/**
 * Projet: faktury
 * Author: Matej Sajgal
 * Date: 27.10.2013
 */

namespace AccountModule;

use EventCalendar\Simple\SimpleCalendar;
use Kdyby\BootstrapFormRenderer\BootstrapRenderer;
use Nette\Application\UI\Form;
use Nette\DateTime;
use Nette\Diagnostics\Debugger;

class TimePresenter extends BasePresenter
{
    /**
     * @var \TimesheetRepository
     */
    private $timesheetRepository;
    /**
     * @var \ProjectRepository
     */
    private $projectRepository;

    private $year;
    private $month;
    private $projects;
    private $userId;
    /**
     * @var \Timesheet_dataRepository
     */
    private $timesheetDataRepository;

    public function injectDefault(
        \TimesheetRepository $timesheetRepository,
        \ProjectRepository $projectRepository,
        \Timesheet_dataRepository $timesheetDataRepository
    )
    {
        $this->timesheetRepository = $timesheetRepository;
        $this->projectRepository = $projectRepository;
        $this->timesheetDataRepository = $timesheetDataRepository;
    }

    public function startup()
    {
        parent::startup();

        $get = $this->getHttpRequest()->getQuery();
        if( isset($get['month']) && !empty($get['month']) && isset($get['year']) && !empty($get['year']) ) {
            $this->month = $get['month'];
            $this->year = $get['year'];
        } else {
            $this->month = date('m');
            $this->year = date('Y');
        }

        $this->userId = $this->user->getId();
        $this->projects = $this->projectRepository->getProjectsForUser($this->userId);
    }

    public function actionDefault($id)
    {
        $isMine = $this->timesheetRepository->isMineAndTodayTimesheet($id, $this->userId);
        if($id && !$isMine) {
            $this->flashMessage('Záznam neexistuje.', 'danger');
            $this->redirect(':Account:time:');
        }

        $this->template->daysInMonth = cal_days_in_month(CAL_GREGORIAN, $this->month, $this->year);
        $this->template->monthlyTimesheets = $this->timesheetRepository->getMonthlyTimesheetArray($this->month, $this->year, $this->userId);
    }

    public function handleDelete($id)
    {
        $this->timesheetRepository->deleteTimeSheet($id, $this->userId);
        $this->flashMessage('Záznam úspešne zmazaný.', 'success');
        $this->redirect(':Account:time:');
    }

    public function renderDefault()
    {
        $lunchTime = $this->timesheetDataRepository->getLunchTime($this->userId, date('Y-m-d'));
        if($lunchTime) {
            $lunchInMinutes = $lunchTime->lunch_in_minutes;
        } else {
            $lunchInMinutes = 0;
        }

        $todaysTimesheets = $this->timesheetRepository->getTodaysTimesheets($this->userId);
        $this->template->todaysTimesheets = $todaysTimesheets;
        $this->template->projects = $this->projects;
        $this->template->todayWorktime = $this->timesheetRepository->getWorkHours(
            $this->userId,
            $lunchInMinutes
        );
        $this->template->monthlyWorktime = $this->timesheetRepository->getWorkHours(
            $this->userId,
            $this->timesheetDataRepository->getMonthlyLunchTime($this->userId, $this->month, $this->year),
            $this->month,
            $this->year
        );
        $this->template->timesheetData = $this->timesheetDataRepository->getMonthDataArray($this->userId, $this->month, $this->year);
        $this->template->month = $this->month;
        $this->template->year = $this->year;

        $this->template->nextDate = $this->timesheetRepository->getNextDate($this->month, $this->year);
        $this->template->prevDate = $this->timesheetRepository->getPrevDate($this->month, $this->year);
    }

    protected function createComponentInsertEditTimeForm()
    {
        $projects = $this->projects;
        $datetimeFormat = 'hh:mm';

        $timeRowId = $this->getParameter('id');

        $form = new Form();
//        $form->setRenderer(new BootstrapRenderer());

        $form->addSelect('project_id', 'Projekt', $projects)
            ->setPrompt('- vyber -')
            ->setRequired('Zadajte projekt prosím');
        $form->addTextArea('description', 'Popis')->setAttribute('placeholder', 'Popis práce')->setRequired('Prosím napíš čo si robil.');
        $form->addText('from', 'Od')->setAttribute('data-format', $datetimeFormat);
        $form->addText('to', 'Do')->setAttribute('data-format', $datetimeFormat);
        $form->addHidden('row_id', $timeRowId);

        $form->onSuccess[] = $this->timeFormSubmitted;

        if ($timeRowId) {
            $form->addSubmit('submit', 'Uložiť');
            $timesheetData = $this->timesheetRepository->fetchById($timeRowId);
            if($timesheetData) {
                $form->setDefaults($timesheetData);
                if($timesheetData->to) {
                    $form['to']->setDefaultValue($timesheetData->to->format('H:i'));
                }
                if($timesheetData->from) {
                    $form['from']->setDefaultValue($timesheetData->from->format('H:i'));
                }
            } else {
                $form->addError('Záznam neexistuje.');
            }
        } else {
            $form->addSubmit('submit', 'Vložiť');
        }

        $form['submit']->setAttribute('class', 'btn btn-success');

        return $form;
    }

    public function timeFormSubmitted(Form $form)
    {
        $values = $form->getValues();
        $today = date('Y-m-d');
        $row_id = $values->row_id;
        unset($values->row_id);

        $values->user_id = $this->userId;
        $values->last_update = new DateTime();
        if($values->from) {
            $values->from = $today . ' ' . $values->from;
        } else {
            unset($values->from);
        }

        if($values->to) {
            $values->to = $today . ' ' . $values->to;
        } else {
            unset($values->to);
        }

        if(!empty($row_id)) {
            //update
            $this->timesheetRepository->updateTimeRow($row_id, $this->userId, $values);
            $this->flashMessage('Záznam úspešne upravený.', 'success');
            $this->redirect(':Account:time:');
        } else {
            //insert
            $this->timesheetRepository->insertTimeRow($values);
            $this->flashMessage('Záznam úspešne vložený.', 'success');
            $this->redirect(':Account:time:');
        }
    }

    protected function createComponentInsertEditLunchTimeForm()
    {
        $form = new Form();
        $form->addText('hours', 'Hodiny');
        $form->addText('minutes', 'Minúty');
        $form->onSuccess[] = $this->lunchTimeFormSubmitted;
        $form->addSubmit('submit', 'Uložiť obed')->setAttribute('class', 'btn btn-success full-width');

        $lunchTime = $this->timesheetDataRepository->getLunchTime($this->userId, date('Y-m-d'));
        if($lunchTime) {
            $form['hours']->setDefaultValue((int)($lunchTime->lunch_in_minutes/60));
            $form['minutes']->setDefaultValue($lunchTime->lunch_in_minutes%60);
        }

        return $form;
    }

    public function lunchTimeFormSubmitted(Form $form)
    {
        $values = $form->getValues();

        $minutesToSave = (int)$values->minutes + (60 * (int)$values->hours);
        $this->timesheetDataRepository->setLunchTime($this->userId, $minutesToSave);

        $this->redirect(':Account:Time:');
    }
}