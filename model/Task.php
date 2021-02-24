<?php declare(strict_types=1);

class TaskException extends Exception {}

class Task {
    private $_id;
    private $_title;
    private $_description;
    private $_deadline;
    private $_complited;

    public function __construct(
        $id,
        $title,
        string $description = null,
        string $deadline = null,
        string $complited)
    {
        $this->setID($id);
        $this->setTitle($title);
        $this->setDescription($description);
        $this->setDeadline($deadline);
        $this->setCompleted($complited);
    }

    public function getID(){
        return $this->_id;
    }

    public function getTitle(){
        return $this->_title;
    }

    public function getDescription(){
        return $this->_description;
    }

    public function getDeadline(){
        return $this->_deadline;
    }

    public function getComplited(){
        return $this->_complited;
    }

    /**
     * @param $id - the task id if provided (not present on new task)
     * @throws TaskException
     */
    public function setID ($id): void
    {
        if( ($id !== null) &&
            (!is_numeric($id) || $id <= 0 || $id > 922337203685477580 || $this->_id !== null)
        ){
            throw new TaskException('Task ID error');
        }
        $this->_id = $id;
    }

    /**
     * @param $title - the task title if provided
     * @throws TaskException
     */
    public function setTitle(string $title): void
    {
        if(strlen($title)  == 0 || strlen($title) > 255){
            throw new TaskException('Task title error');
        }
        $this->_title = $title;
    }

    /**
     * @param string|null $description - the task description if provided
     * @throws TaskException
     */
    public function setDescription(string $description=null): void
    {
        if($description !== null && strlen($description) > 16777215){
            throw new TaskException('Task description error');
        }
        $this->_description = $description;
    }

    /**
     * @param string|null $deadline - the task time if provided as string in form 'd/m/Y H:i'
     * @throws TaskException
     */
    public function setDeadline(string $deadline=null): void
    {
        if( ($deadline !== null) &&
            date_format(
                date_create_from_format('d/m/Y H:i', $deadline),
                'd/m/Y H:i'
            ) !== $deadline
        ){
            throw new TaskException('Task deadline date time error');
        }
        $this->_deadline = $deadline;
    }

    /**
     * @param string $completed - the Y or N for the task completed status
     * @throws TaskException
     */
    public function setCompleted(string $completed): void
    {
        if(strtoupper($completed) !== 'Y' && strtoupper($completed) !== 'N'){
            throw new TaskException('Task completed must be Y or N');
        }
        $this->_complited = $completed;
    }

    /**
     * @param none;
     * @return array
     */
    public function returnTaskAsArray(): array
    {
        $task                = [];
        $task['id']          = $this->getID();
        $task['title']       = $this->getTitle();
        $task['description'] = $this->getDescription();
        $task['deadline']    = $this->getDeadline();
        $task['completed']   = $this->getComplited();

        return $task;
    }


} // class