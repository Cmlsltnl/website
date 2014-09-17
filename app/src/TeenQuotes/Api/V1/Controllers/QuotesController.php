<?php
namespace TeenQuotes\Api\V1\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use LucaDegasperi\OAuth2Server\Facades\ResourceServerFacade as ResourceServer;
use \Quote;
use \User;

class QuotesController extends APIGlobalController {

	public function getSingleQuote($quote_id)
	{
		$quote = Quote::whereId($quote_id)
		->with('comments')
		->withSmallUser('comments.user')
		->withSmallUser('favorites.user')
		->withSmallUser()
		->first();

		// Handle not found
		if (empty($quote) OR $quote->count() == 0) {

			$data = [
				'status' => 'quote_not_found',
				'error'  => "The quote #".$quote_id." was not found",
			];

			return Response::json($data, 404);
		}
		
		// Register the view in the recommendation engine
		$quote->registerViewAction();

		return Response::json($quote, 200, [], JSON_NUMERIC_CHECK);
	}

	public function indexFavoritesQuotes($user_id)
	{
		$page = Input::get('page', 1);
		$pagesize = Input::get('pagesize', Config::get('app.users.nbQuotesPerPage'));

        if ($page <= 0)
			$page = 1;

		$user = User::find($user_id);
		
		// Handle user not found
		if (is_null($user)) {
			$data = [
				'status' => 'user_not_found',
				'error'  => "The user #".$user_id." was not found",
			];

			return Response::json($data, 400);
		}

		// Get the list of favorite quotes
		$arrayIDFavoritesQuotesForUser = $user->arrayIDFavoritesQuotes();

		$totalQuotes = count($arrayIDFavoritesQuotesForUser);
		
		// Get quotes
		$content = array();
		if ($totalQuotes > 0)
			$content = $this->getQuotesFavorite($page, $pagesize, $user, $arrayIDFavoritesQuotesForUser);

		// Handle no quotes found
		if (is_null($content) OR empty($content) OR $content->count() == 0) {
			$data = [
				'status' => 404,
				'error' => 'No quotes have been found.'
			];

			return Response::json($data, 404);
		}

		$data = self::paginateContent($page, $pagesize, $totalQuotes, $content, 'quotes');
		
		return Response::json($data, 200, [], JSON_NUMERIC_CHECK);
	}

	public function indexByApprovedQuotes($quote_approved_type, $user_id)
	{
		$page = Input::get('page', 1);
		$pagesize = Input::get('pagesize', Config::get('app.users.nbQuotesPerPage'));

        if ($page <= 0)
			$page = 1;

		$user = User::find($user_id);
		
		// Handle user not found
		if (is_null($user)) {
			$data = [
				'status' => 'user_not_found',
				'error'  => "The user #".$user_id." was not found",
			];

			return Response::json($data, 400);
		}
		
		// Get quotes
		$content = $this->getQuotesByApprovedForUser($page, $pagesize, $user, $quote_approved_type);

		// Handle no quotes found
		$totalQuotes = 0;
		if (is_null($content) OR empty($content) OR $content->count() == 0) {
			$data = [
				'status' => 404,
				'error' => 'No quotes have been found.'
			];

			return Response::json($data, 404);
		}

		$totalQuotes = Quote::$quote_approved_type()->forUser($user)->count();

		$data = self::paginateContent($page, $pagesize, $totalQuotes, $content, 'quotes');
		
		return Response::json($data, 200, [], JSON_NUMERIC_CHECK);
	}


	public function indexQuotes($random = null)
	{
		$page = Input::get('page', 1);
		$pagesize = Input::get('pagesize', Config::get('app.quotes.nbQuotesPerPage'));

        if ($page <= 0)
			$page = 1;

		$totalQuotes = Quote::nbQuotesPublished();

        // Get quotes
        if (is_null($random))
        	$content = $this->getQuotesHome($page, $pagesize);
        else
        	$content = $this->getQuotesRandom($page, $pagesize);

		// Handle no quotes found
		if (is_null($content) OR $content->count() == 0) {
			$data = [
				'status' => 404,
				'error' => 'No quotes have been found.'
			];

			return Response::json($data, 404);
		}

		$data = self::paginateContent($page, $pagesize, $totalQuotes, $content, 'quotes');
		
		return Response::json($data, 200, [], JSON_NUMERIC_CHECK);
	}

	public function getSearch($query)
	{
		$page = Input::get('page', 1);
		$pagesize = Input::get('pagesize', Config::get('app.quotes.nbQuotesPerPage'));

        if ($page <= 0)
			$page = 1;
				
		// Get quotes
		$content = self::getQuotesSearch($page, $pagesize, $query);

		// Handle no quotes found
		$totalQuotes = 0;
		if (is_null($content) OR empty($content) OR $content->count() == 0) {
			$data = [
				'status' => 404,
				'error' => 'No quotes have been found.'
			];

			return Response::json($data, 404);
		}

		$totalQuotes = Quote::
		// $query will NOT be bind here
		// it will be bind when calling setBindings
		whereRaw("MATCH(content) AGAINST(?)", array($query))
		->where('approved', '=', 1)
		// WARNING 1 corresponds to approved = 1
		// We need to bind it again
		->setBindings([$query, 1])
		->count();

		$data = self::paginateContent($page, $pagesize, $totalQuotes, $content, 'quotes');
		
		return Response::json($data, 200, [], JSON_NUMERIC_CHECK);
	}

	public function postStoreQuote($doValidation = true)
	{
		$user = $this->retrieveUser();
		$content = Input::get('content');

		if ($doValidation) {		
			// Validate content of the quote
			$validatorContent = Validator::make(compact('content'), ['content' => Quote::$rulesAdd['content']]);
			if ($validatorContent->fails()) {
				$data = [
					'status' => 'wrong_content',
					'error'  => 'Content of the quote should be between 50 and 300 characters'
				];

				return Response::json($data, 400);
			}

			// Validate number of quotes submitted today
			$quotesSubmittedToday = Quote::createdToday()->forUser($user)->count();
			$validatorNbQuotes = Validator::make(compact('quotesSubmittedToday'), ['quotesSubmittedToday' => Quote::$rulesAdd['quotesSubmittedToday']]);
			if ($validatorNbQuotes->fails()) {
				$data = [
					'status' => 'too_much_submitted_quotes',
					'error'  => "The maximum number of quotes you can submit is 5 per day"
				];

				return Response::json($data, 400);
			}
		}

		// Store the quote
		$quote = new Quote;
		$quote->content = $content;
		$user->quotes()->save($quote);

		return Response::json($quote, 200, [], JSON_NUMERIC_CHECK);
	}

	private function getQuotesHome($page, $pagesize)
	{
		// Time to store in cache
		$expiresAt = Carbon::now()->addMinutes(1);

        // Number of quotes to skip
        $skip = $pagesize * ($page - 1);

        if ($pagesize == Config::get('app.quotes.nbQuotesPerPage')) {

        	$content = Cache::remember(Quote::$cacheNameQuotesAPIPage.$page, $expiresAt, function() use($pagesize, $skip)
        	{
		        return Quote::published()
				->withSmallUser()
				->orderDescending()
				->take($pagesize)
				->skip($skip)
				->get();
        	});
        }
        else {
        	$content = Quote::published()
				->withSmallUser()
				->orderDescending()
				->take($pagesize)
				->skip($skip)
				->get();
        }

        return $content;
	}

	private function getQuotesRandom($page, $pagesize)
	{
		// Time to store in cache
		$expiresAt = Carbon::now()->addMinutes(1);

        // Number of quotes to skip
        $skip = $pagesize * ($page - 1);

        if ($pagesize == Config::get('app.quotes.nbQuotesPerPage')) {

        	$content = Cache::remember(Quote::$cacheNameRandomAPIPage.$page, $expiresAt, function() use($pagesize, $skip)
        	{
		        return Quote::published()
				->withSmallUser()
				->random()
				->take($pagesize)
				->skip($skip)
				->get();
        	});
        }
        else {
        	$content = Quote::published()
				->withSmallUser()
				->random()
				->take($pagesize)
				->skip($skip)
				->get();
        }

        return $content;
	}

	private function getQuotesFavorite($page, $pagesize, $user, $arrayIDFavoritesQuotesForUser)
	{
		// Number of quotes to skip
        $skip = $pagesize * ($page - 1);

		$content = Quote::whereIn('id', $arrayIDFavoritesQuotesForUser)
			->withSmallUser()
			->orderBy(DB::raw("FIELD(id, ".implode(',', $arrayIDFavoritesQuotesForUser).")"))
			->take($pagesize)
			->skip($skip)
			->get();

		return $content;
	}

	public static function getQuotesSearch($page, $pagesize, $query)
	{
		// Number of quotes to skip
        $skip = $pagesize * ($page - 1);

        $quotes = Quote::
		select('id', 'content', 'user_id', 'approved', 'created_at', 'updated_at', DB::raw("MATCH(content) AGAINST(?) AS `rank`"))
		// $search will NOT be bind here
		// it will be bind when calling setBindings
		->whereRaw("MATCH(content) AGAINST(?)", array($query))
		->where('approved', '=', 1)
		->orderBy('rank', 'DESC')
		->withSmallUser()
		->skip($skip)
		->take($pagesize)
		// WARNING 1 corresponds to approved = 1
		// We need to bind it again
		->setBindings([$query, $query, 1])
		->get();

		return $quotes;
	}

	private function getQuotesByApprovedForUser($page, $pagesize, $user, $quote_approved_type)
	{
		// Number of quotes to skip
        $skip = $pagesize * ($page - 1);

		$content = Quote::$quote_approved_type()
			->withSmallUser()
			->forUser($user)
			->orderDescending()
			->take($pagesize)
			->skip($skip)
			->get();

		return $content;
	}
}