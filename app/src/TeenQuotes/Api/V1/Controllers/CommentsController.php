<?php namespace TeenQuotes\Api\V1\Controllers;

use Comment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use LucaDegasperi\OAuth2Server\Facades\ResourceServerFacade as ResourceServer;
use Quote;
use TeenQuotes\Mail\MailSwitcher;
use User;

class CommentsController extends APIGlobalController {

	public function index($quote_id)
	{
		$page = max(1, Input::get('page', 1));
		$pagesize = Input::get('pagesize', Config::get('app.comments.nbCommentsPerPage'));
		
		// Number of comments to skip
		$skip = $pagesize * ($page - 1);

		$totalComments = Comment::forQuoteId($quote_id)->count();

		// Get comments
		$contentQuery = Comment::forQuoteId($quote_id)
			->withSmallUser()
			->orderDescending();

		if (Input::has('quote'))
			$contentQuery = $contentQuery->with('quote');

		$content = $contentQuery->take($pagesize)
			->skip($skip)
			->get();

		// Handle no comments found
		if (is_null($content) OR $content->count() == 0)
			return Response::json([
				'status' => 404,
				'error' => 'No comments have been found.'
			], 404);

		$data = self::paginateContent($page, $pagesize, $totalComments, $content, 'comments');
		
		return Response::json($data, 200, [], JSON_NUMERIC_CHECK);
	}

	public function show($comment_id)
	{
		$commentQuery = Comment::where('id', '=', $comment_id)
			->withSmallUser();
					
		if (Input::has('quote'))
			$commentQuery = $commentQuery->with('quote');
		
		$comment = $commentQuery->first();

		// Handle not found
		if (is_null($comment))
			return Response::json([
				'status' => 'comment_not_found',
				'error'  => "The comment #".$comment_id." was not found",
			], 404);
		
		return Response::json($comment, 200, [], JSON_NUMERIC_CHECK);
	}

	public function store($quote_id, $doValidation = true)
	{
		$user = $this->retrieveUser();
		$content = Input::get('content');

		if ($doValidation) {

			// Validate quote_id and content
			foreach (['quote_id', 'content'] as $value) {
				$validator = Validator::make(compact($value), [$value => Comment::$rulesAdd[$value]]);
				if ($validator->fails())
					return Response::json([
						'status' => 'wrong_'.$value,
						'error' => $validator->messages()->first($value)
					], 400);
			}
		}

		$quote = Quote::where('id', '=', $quote_id)
			->with('user')
			->first();
		
		// Check if the quote is published
		if ( ! $quote->isPublished())
			return Response::json([
				'status' => 'wrong_quote_id',
				'error' => 'The quote should be published.'
			], 400);

		// Store the comment
		$comment = new Comment;
		$comment->content  = $content;
		$comment->quote_id = $quote_id;
		$comment->user_id  = $user->id;
		$comment->save();

		// Send an e-mail to the author of the quote if he wants it
		// Delete number of comments in cache

		return Response::json($comment, 201, [], JSON_NUMERIC_CHECK);
	}

	public function destroy($id)
	{
		$user = $this->retrieveUser();
		$comment = Comment::find($id);

		// Handle not found
		if (is_null($comment))
			return Response::json([
				'status' => 'comment_not_found',
				'error'  => "The comment #".$id." was not found.",
			], 404);

		// Check that the user is the owner of the comment
		if ( ! $comment->isPostedByUser($user))
			return Response::json([
				'status' => 'comment_not_self',
				'error'  => "The comment #".$id." was not posted by user #".$user->id.".",
			], 400);

		// Delete the comment
		$comment->delete();
		
		// Decrease the number of comments on the quote in cache

		return Response::json([
			'status'  => 'comment_deleted',
			'success' => "The comment #".$id." was deleted.",
		], 200);
	}
}