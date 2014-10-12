<?php

use Illuminate\Console\Command;
use Indatus\Dispatcher\Drivers\Cron\Scheduler;
use Indatus\Dispatcher\Scheduling\Schedulable;
use Indatus\Dispatcher\Scheduling\ScheduledCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use TeenQuotes\Newsletters\Models\Newsletter;

class SendNewsletterCommand extends ScheduledCommand {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'newsletter:send';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Send a newsletter to the subscribed users.';

	/**
	 * Allowed event types
	 * @var array
	 */
	private $possibleTypes = ['daily', 'weekly'];

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * When a command should run
	 *
	 * @param Scheduler $scheduler
	 * @return \Indatus\Dispatcher\Scheduling\Schedulable
	 */
	public function schedule(Schedulable $scheduler)
	{
		return [
			$scheduler
				->args(['daily'])
				->daily()
				->hours(12)
				->minutes(0),

			$scheduler
				->args(['weekly'])
				->daysOfTheWeek([
					Scheduler::MONDAY])
				->hours(12)
				->minutes(15),
		];
	}

	/**
	 * Choose the environment(s) where the command should run
	 * @return array Array of environments' name
	 */
	public function environment()
	{
		return ['production'];
	}

	/**
	 * Execute the console command.
	 *
	 */
	public function fire()
	{
		if ($this->eventTypeIsValid()) {		
			$type = $this->getType();

			$quotes = ($type == 'weekly') ? $this->retrieveWeeklyQuotes() : $this->retrieveDailyQuotes();

			// Send the newsletter only if we have
			// at least 1 quote
			if ( ! $quotes->isEmpty()) {

				// Get users that are subscribed to the newsletter
				$rowNewsletters = Newsletter::whereType($type)
					->with('user')
					->get();
				
				$rowNewsletters->each(function($newsletter) use($type, $quotes)
				{
					// Log this info
					$this->info("Send ".$type." newsletter to ".$newsletter->user->login." - ".$newsletter->user->email);
					Log::info("Send ".$type." newsletter to ".$newsletter->user->login." - ".$newsletter->user->email);

					// Send the email to the user
					Mail::send('emails.newsletters.'.$type, compact('newsletter', 'quotes'), function($m) use($newsletter, $type)
					{
						$m->to($newsletter->user->email, $newsletter->user->login)->subject(Lang::get('newsletters.'.$type.'SubjectEmail'));
					});
				});
			}
		}
	}

	private function retrieveWeeklyQuotes()
	{
		$nbQuotes = $this->getNbQuotes();
		
		$quotes = Quote::published()
			->with('user')
			->random()
			->take($nbQuotes)
			->get();

		return $quotes;
	}

	private function retrieveDailyQuotes()
	{
		$nbQuotes = $this->getNbQuotes();
		
		$quotes = Quote::published()
			->updatedToday()
			->random()
			->with('user')
			->take($nbQuotes)
			->get();

		return $quotes;
	}

	private function getType()
	{
		return $this->argument('type');
	}

	private function getNbQuotes()
	{
		$type = $this->getType();

		// Get the number of quotes to publish
		$nbQuotes = is_null($this->argument('nb_quotes')) ? Config::get('app.newsletters.nbQuotesToSend'.ucfirst($type)) : $this->argument('nb_quotes');

		return $nbQuotes;
	}

	private function eventTypeIsValid()
	{
		$type = $this->getType();

		if (is_null($type) OR !in_array($type, $this->possibleTypes)) {
			$this->error('Wrong type for the newsletter! Can only be '.$this->presentPossibleTypes().'. '.$type.' was given.');
			return false;
		}

		return true;
	}

	private function presentPossibleTypes()
	{
		return implode('|', $this->possibleTypes);
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array(
			array('type', InputArgument::REQUIRED, 'The type of newsletter we will send. '.$this->presentPossibleTypes()),
			array('nb_quotes', InputArgument::OPTIONAL, 'The number of quotes to send.'),
		);
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array();
	}
}
