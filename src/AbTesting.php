<?php

namespace Gleman17\AbTesting;

use Gleman17\AbTesting\Models\Goal;
use Illuminate\Support\Collection;
use Gleman17\AbTesting\Models\Experiment;
use Gleman17\AbTesting\Events\GoalCompleted;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Gleman17\AbTesting\Events\ExperimentNewVisitor;
use Gleman17\AbTesting\Exceptions\InvalidConfiguration;

class AbTesting
{
    protected $experiments;

    const SESSION_KEY_EXPERIMENT = 'ab_testing_experiment';
    const SESSION_KEY_GOALS = 'ab_testing_goals';

    public function __construct()
    {
        $this->experiments = new Collection;
    }

    /**
     * Validates the config items and puts them into models.
     *
     * @return void
     */
    protected function start()
    {
        $configExperiments = config('ab-testing.experiments');
        $configGoals = config('ab-testing.goals');

        if (! count($configExperiments)) {
            throw InvalidConfiguration::noExperiment();
        }

        if (count($configExperiments) !== count(array_unique($configExperiments))) {
            throw InvalidConfiguration::experiment();
        }

        if (count($configGoals) !== count(array_unique($configGoals))) {
            throw InvalidConfiguration::goal();
        }

        foreach ($configExperiments as $configExperiment) {
            $this->experiments[] = $experiment = Experiment::firstOrCreate([
                'name' => $configExperiment,
            ], [
                'visitors' => 0,
            ]);

            foreach ($configGoals as $configGoal) {
                $experiment->goals()->firstOrCreate([
                    'name' => $configGoal,
                ], [
                    'hit' => 0,
                ]);
            }
        }

        session([
            self::SESSION_KEY_GOALS => new Collection,
        ]);
    }

    /**
     * Triggers a new visitor. Picks a new experiment and saves it to the session.
     *
     * @return \Gleman17\AbTesting\Models\Experiment|void
     */
    public function pageView()
    {
        if (config('ab-testing.ignore_crawlers') && (new CrawlerDetect)->isCrawler()) {
            return;
        }

        if (session(self::SESSION_KEY_EXPERIMENT)) {
            return;
        }

        $this->start();
        $this->setNextExperiment();

        event(new ExperimentNewVisitor($this->getExperiment()));

        return $this->getExperiment();
    }

    /**
     * Calculates a new experiment and sets it to the session.
     *
     * @return void
     */
    protected function setNextExperiment()
    {
        $next = $this->getNextExperiment();
        $next->incrementVisitor();

        session([
            self::SESSION_KEY_EXPERIMENT => $next,
        ]);
    }

    /**
     * Calculates a new experiment.
     *
     * @return \Gleman17\AbTesting\Models\Experiment|null
     */
    protected function getNextExperiment()
    {
        $sorted = $this->experiments->sortBy('visitors');

        return $sorted->first();
    }

    /**
     * Checks if the currently active experiment is the given one.
     *
     * @param string $name The experiments name
     *
     * @return bool
     */
    public function isExperiment(string $name)
    {
        $this->pageView();
        $experiment = $this->getExperiment();
        if ($experiment === null){
            return false;
        }
        return $experiment->name === $name;
    }

    /**
     * Completes a goal by incrementing the hit property of the model and setting its ID in the session.
     *
     * @param string $goal The goals name
     *
     * @return \Gleman17\AbTesting\Models\Goal|false
     */
    public function completeGoal(string $goal)
    {
        if (! $this->getExperiment()) {
            $this->pageView();
        }

        $experiment = $this->getExperiment();
        if ($experiment === null){
            return false;
        }

        $goal = $experiment->goals->where('name', $goal)->first();

        if (! $goal) {
            return false;
        }

        if (session(self::SESSION_KEY_GOALS)->contains($goal->id)) {
            return false;
        }

        session(self::SESSION_KEY_GOALS)->push($goal->id);

        $goal->incrementHit();
        event(new GoalCompleted($goal));

        return $goal;
    }

    /**
     * Returns the currently active experiment.
     *
     * @return \Gleman17\AbTesting\Models\Experiment|null
     */
    public function getExperiment()
    {
        return session(self::SESSION_KEY_EXPERIMENT);
    }

    /**
     * Returns all the completed goals.
     *
     * @return \Illuminate\Support\Collection|false
     */
    public function getCompletedGoals()
    {
        if (! session(self::SESSION_KEY_GOALS)) {
            return false;
        }

        return session(self::SESSION_KEY_GOALS)->map(function ($goalId) {
            return Goal::find($goalId);
        });
    }
}
