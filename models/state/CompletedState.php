<?php


namespace humhub\modules\tasks\models\state;


use humhub\modules\tasks\activities\TaskCompletedActivity;
use humhub\modules\tasks\activities\TaskReviewedActivity;
use humhub\modules\tasks\models\Task;
use humhub\modules\tasks\notifications\TaskCompletedNotification;
use humhub\modules\tasks\notifications\ReviewSuccessNotification;
use Yii;

class CompletedState extends TaskState
{
    public static $status = Task::STATUS_COMPLETED;

    public function checkProceedRules($newState = null, $user = null)
    {
        return false;
    }

    protected function proceedConfig($user = null)
    {
        return [];
    }

    protected function revertConfig($user = null)
    {
        return [
            Task::STATUS_PENDING_REVIEW => [
                'label' => Yii::t('TasksModule.base', 'Continue Review'),
                'icon' => 'fa-eye'
            ],
            Task::STATUS_PENDING => [
                'label' => Yii::t('TasksModule.base', 'Reset Task'),
                'icon' => 'fa-undo'
            ],
        ];
    }

    protected function getDefaultRevertStatusId()
    {
        return $this->task->review ? Task::STATUS_PENDING_REVIEW : Task::STATUS_PENDING;
    }

    public function checkRevertRules($newStatus = null, $user = null)
    {
        return $this->canCompleteTask($user);
    }

    public function canCompleteTask($user = null)
    {
        if($this->task->isTaskResponsible($user) || $this->task->isOwner($user)) {
            return true;
        } else if(!$this->task->review) {
            return $this->task->isTaskAssigned($user) || $this->task->canAnyoneProcessTask($user);
        }

        return false;
    }

    public function afterProceed(TaskState $oldState)
    {
        if ($this->task->hasItems()) {
            $this->task->completeItems();
        }
        $this->notifyCompleted();
    }

    /**
     * Notify users about status change
     */
    public function notifyCompleted()
    {
        $user = Yii::$app->user->getIdentity();

        if ($this->task->review) {
            if ($this->task->hasTaskAssigned()) {
                ReviewSuccessNotification::instance()->from($user)->about($this)->sendBulk($this->task->getTaskAssignedUsers(true));
            }

            if ($this->task->hasTaskResponsible()) {
                ReviewSuccessNotification::instance()->from($user)->about($this->task)->sendBulk($this->task->taskResponsibleUsers);
            }

            TaskReviewedActivity::instance()->from($user)->about($this->task)->create();
        } else {
            if ($this->task->hasTaskResponsible()) {
                TaskCompletedNotification::instance()->from($user)->about($this->task)->sendBulk($this->task->taskResponsibleUsers);
            }

            TaskCompletedActivity::instance()->from($user)->about($this->task)->create();
        }
    }

    public function afterRevert(TaskState $oldState)
    {
        //Nothing to do...
    }
}