<?php
/**
 * Projet: faktury
 * Author: Matej Sajgal
 * Date: 27.10.2013
 */

namespace AccountModule;


use Kdyby\BootstrapFormRenderer\BootstrapRenderer;
use Nette\Application\UI\Form;
use Nette\DateTime;

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

    public function injectDefault(
        \TimesheetRepository $timesheetRepository,
        \ProjectRepository $projectRepository
    )
    {
        $this->timesheetRepository = $timesheetRepository;
        $this->projectRepository = $projectRepository;
    }

    public function startup()
    {
        parent::startup();
        $this->month = date('m');
        $this->year = date('Y');
        $this->projects = $this->projectRepository->getProjectsForUser($this->user->getId());
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
        $form->addTextArea('description', 'Popis');
        $form->addText('from', 'Od')->setAttribute('data-format', $datetimeFormat);
        $form->addText('to', 'Do')->setAttribute('data-format', $datetimeFormat);
        $form->addHidden('row_id', $timeRowId);

        $form->onSuccess[] = $this->timeFormSubmitted;

        if ($timeRowId) {
            $form->addSubmit('submit', 'Uložiť');
//            $projectId = $this->getParameter('id');
//            $projectData = $this->projectRepository->fetchById($projectId);
//            $form->setDefaults($projectData);
        } else {
            $form->addSubmit('submit', 'Vložiť');
        }

        $form['submit']->setAttribute('class', 'btn btn-primary');



        return $form;
    }

    public function timeFormSubmitted(Form $form)
    {
        $values = $form->getValues();
        $row_id = $values->row_id;
        unset($values->row_id);

        $values->user_id = $this->user->getId();
        $values->last_update = new DateTime();

        if(!empty($row_id)) {
            //update
        } else {
            //insert
            $this->timesheetRepository->insertTimeRow($values);
            $this->flashMessage('Záznam úspešne vložený.', 'success');
            $this->redirect(':Account:time:');
        }
    }
}