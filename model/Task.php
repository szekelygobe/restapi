<?php declare(strict_types=1);

class TaskException extends Exception {}

class Task {
    private $_id;
    private $_userid;
    private $_title;
    private $_description;
    private $_deadline;
    private $_complited;

    public function __construct(
        $id,
        $userid,
        $title,
        string $description = null,
        string $deadline = null,
        string $complited)
    {
        $this->setID($id);
        $this->setUserID($userid);
        $this->setTitle($title);
        $this->setDescription($description);
        $this->setDeadline($deadline);
        $this->setCompleted($complited);
    }


    public static function createFromArray(array $p_task):Task
    {
        // building new task from param array
        return new self(
            $p_task['id'],
            $p_task['userid'],
            $p_task['title'],
            $p_task['description'],
            $p_task['deadline'],
            $p_task['completed']
        );
    } // end from array

    public function getID(){
        return $this->_id;
    }

    public function getUserID(){
        return $this->_userid;
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
     * @param $userid - the userid, owner of the task
     * @throws TaskException
     */
    public function setUserID ($userid): void
    {
        if( $userid <= 0 || $userid > 922337203685477580 ){
            throw new TaskException('User ID error');
        }
        $this->_userid = $userid;
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
                date_create_from_format(CONST_PHP_DATE_FORMAT, $deadline),
                CONST_PHP_DATE_FORMAT
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
        $task['userid']      = $this->getUserID();
        $task['title']       = $this->getTitle();
        $task['description'] = $this->getDescription();
        $task['deadline']    = $this->getDeadline();
        $task['completed']   = $this->getComplited();

        return $task;
    }


} // class