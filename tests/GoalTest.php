<?php

namespace gleman17\AbTesting\Tests;

use gleman17\AbTesting\AbTesting;
use gleman17\AbTesting\AbTestingFacade;
use Illuminate\Support\Facades\Event;
use gleman17\AbTesting\Events\GoalCompleted;

class GoalTest extends TestCase
{
    public function test_that_goal_complete_works()
    {
        $returnedGoal = AbTestingFacade::completeGoal('firstGoal');

        $experiment = session(AbTesting::SESSION_KEY_EXPERIMENT);
        $goal = $experiment->goals->where('name', 'firstGoal')->first();

        $this->assertEquals($goal, $returnedGoal);

        $this->assertEquals(1, $goal->hit);

        $this->assertEquals(collect([$goal->id]), session(AbTesting::SESSION_KEY_GOALS));

        Event::assertDispatched(GoalCompleted::class, function ($g) use ($goal) {
            return $g->goal->id === $goal->id;
        });
    }

    public function test_that_goal_can_only_be_completed_once()
    {
        $this->test_that_goal_complete_works();

        $experiment = session(AbTesting::SESSION_KEY_EXPERIMENT);
        $goal = $experiment->goals->where('name', 'firstGoal')->first();

        $this->assertEquals(1, $goal->hit);

        $returnedGoal = AbTestingFacade::completeGoal('firstGoal');

        $this->assertFalse($returnedGoal);

        $this->assertEquals(1, $goal->hit);

        $this->assertEquals(collect([$goal->id]), session(AbTesting::SESSION_KEY_GOALS));
    }

    public function test_that_invalid_goal_name_returns_false()
    {
        $this->assertFalse(AbTestingFacade::completeGoal('1234'));
    }

    public function test_that_completed_goals_works()
    {
        AbTestingFacade::completeGoal('firstGoal');

        $experiment = session(AbTesting::SESSION_KEY_EXPERIMENT);
        $goal = $experiment->goals->where('name', 'firstGoal');

        $this->assertEquals($goal->pluck('id')->toArray(), AbTestingFacade::getCompletedGoals()->pluck('id')->toArray());
    }
}
