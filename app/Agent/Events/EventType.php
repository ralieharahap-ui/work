<?php

namespace App\Agent\Events;

/** Jenis peristiwa pada jejak audit agent. */
final class EventType
{
    public const TASK_CREATED         = 'TASK_CREATED';
    public const TASK_UNDERSTOOD      = 'TASK_UNDERSTOOD';
    public const MEMORY_RETRIEVED     = 'MEMORY_RETRIEVED';
    public const TASK_PLANNED         = 'TASK_PLANNED';
    public const PLAN_REVISED         = 'PLAN_REVISED';
    public const LESSON_APPLIED       = 'LESSON_APPLIED';
    public const STEP_STARTED         = 'STEP_STARTED';
    public const TOOL_CALLED          = 'TOOL_CALLED';
    public const TOOL_COMPLETED       = 'TOOL_COMPLETED';
    public const TOOL_FAILED          = 'TOOL_FAILED';
    public const TOOL_REPLAYED        = 'TOOL_REPLAYED';
    public const RECOVERY_APPLIED     = 'RECOVERY_APPLIED';
    public const POLICY_BLOCKED       = 'POLICY_BLOCKED';
    public const APPROVAL_REQUESTED   = 'APPROVAL_REQUESTED';
    public const APPROVAL_GRANTED     = 'APPROVAL_GRANTED';
    public const APPROVAL_REJECTED    = 'APPROVAL_REJECTED';
    public const ACCESS_REQUESTED     = 'ACCESS_REQUESTED';
    public const VERIFICATION_STARTED = 'VERIFICATION_STARTED';
    public const VERIFICATION_PASSED  = 'VERIFICATION_PASSED';
    public const VERIFICATION_FAILED  = 'VERIFICATION_FAILED';
    public const HUMAN_REVIEW_REQUESTED = 'HUMAN_REVIEW_REQUESTED';
    public const TASK_PAUSED          = 'TASK_PAUSED';
    public const TASK_RESUMED         = 'TASK_RESUMED';
    public const TASK_CANCELLED       = 'TASK_CANCELLED';
    public const TASK_COMPLETED       = 'TASK_COMPLETED';
    public const TASK_FAILED          = 'TASK_FAILED';
    public const REFLECTION_CREATED   = 'REFLECTION_CREATED';
    public const EXPERIENCE_CREATED   = 'EXPERIENCE_CREATED';
    public const LESSON_CREATED       = 'LESSON_CREATED';
    public const PROCEDURE_UPDATED    = 'PROCEDURE_UPDATED';
    public const MESSAGE_SENT         = 'MESSAGE_SENT';
    public const MESSAGE_RECEIVED     = 'MESSAGE_RECEIVED';
}
