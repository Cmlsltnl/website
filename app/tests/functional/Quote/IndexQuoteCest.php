<?php

class IndexQuoteCest {
	
	/**
	 * The logged in user
	 * @var User
	 */
	private $user;

	/**
	 * The first quote on the first quote
	 * @var Quote
	 */
	private $firstQuote;

	public function _before(FunctionalTester $I)
	{
		$I->logANewUser();
		$I->createSomePublishedQuotes(['nb_quotes' => $this->getTotalNumberOfQuotesToCreate()]);

		// Create a new user, a fresh published quote and some comments to it
		$this->user = $I->logANewUser();
		$this->firstQuote = $I->insertInDatabase(1, 'Quote', ['created_at' => Carbon::now()->addMonth(), 'user_id' => $this->user->id]);
		$I->insertInDatabase($this->getNbComments(), 'Comment', ['quote_id' => $this->firstQuote->id]);
	}

	public function browseLastQuotes(FunctionalTester $I)
	{
		$I->am('a member of Teen Quotes');
		$I->wantTo("browse last quotes");

		$I->amOnRoute('home');

		for ($i = 1; $i <= $this->getNbQuotesPerPage(); $i++) { 
			// Verify that we have got our quotes with different colors
			$I->seeElement('.color-'.$i);
			
			// All of them are not in my favorites
			$I->seeElement('.color-'.$i.' .favorite-action i.fa-heart-o');
			
			// All of them contain social media buttons
			$I->seeElement('.color-'.$i.' i.fa-facebook');
			$I->seeElement('.color-'.$i.' i.fa-twitter');
		}
		
		// I am on the first page
		$I->see('1', '#paginator-quotes ul li.active');
		
		// I can see that we have got our links to pages
		for ($i = 2; $i <= $this->getNbPagesToCreate(); $i++) { 
			$I->see($i, '#paginator-quotes li a');
		}

		// Go to the second page and check that the page
		// parameter has been set in the URL
		$I->click('2', '#paginator-quotes li a');
		$I->seeCurrentUrlMatches('#page=2#');
	}

	public function checkCommentsAndFavoritesAreSet(FunctionalTester $I)
	{
		$I->am('a member of Teen Quotes');
		$I->wantTo("view comments and favorites on last quotes");
		
		// Add to the user's favorites the first quote
		$I->addAFavoriteForUser($this->firstQuote->id, $this->user->id);

		$I->amOnRoute('home');
		// Assert that the number of comments is displayed
		$I->see($this->getNbComments().' comments', '.color-1');
		// Assert that the quote is marked as favorited
		$I->seeElement('.color-1 i.fa-heart');
		// Assert that the author of the quote is displayed
		$I->see($this->user->login, '.color-1 .link-author-profile');

		// I can view my profile when clicking on the author of a quote
		$I->click('.color-1 .link-author-profile');
		$I->seeCurrentRouteIs('users.show', $this->user->login);
	}

	private function getNbComments()
	{
		return 5;
	}

	private function getTotalNumberOfQuotesToCreate()
	{
		return $this->getNbPagesToCreate() * $this->getNbQuotesPerPage();
	}

	private function getNbPagesToCreate()
	{
		return 3;
	}

	private function getNbQuotesPerPage()
	{
		return Config::get('app.quotes.nbQuotesPerPage');
	}
}