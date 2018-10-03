<?php

namespace AppBundle\Controller;

use AppBundle\Entity\SavedGames;
//use AppBundle\Repository\SavedGamesRepository;
use AppBundle\Model\GameModel;
use AppBundle\Tic\Game;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
//use Symfony\Component\HttpFoundation\Request;

class GameController extends Controller {

	/**
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function indexAction() {
		$qb = $this->getDoctrine()->getEntityManager()->createQueryBuilder();
		$latestGame = $qb ->select('sg')
		                  ->from('AppBundle:SavedGames', 'sg')
		                  ->setMaxResults(1)
		                  ->orderBy('sg.id', 'DESC')
		                  ->getQuery()
		                  ->getOneOrNullResult()
		;
		return $this->render(
			'AppBundle:Game:index.html.twig', [
				'latestGameStatus' => $latestGame->getStatus()
			]
		);
	}

	/**
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function restartAction()
	{
		$this->get('session')->clear();
		$places = [];
		$this->get('session')->set('places', $places);
		$this->get('app.model.game')->startGame();
		$game = $this->get('app.model.game')->getGame();

		return $this->render(
			'AppBundle:Game:start.html.twig', array(
			'grid'          => $game->getBoard()->getGrid(),
			'currentPlayer' => $game->getCurrentPlayer(),
		));
	}

	/**
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function startAction() {
		$qb = $this->getDoctrine()->getEntityManager()->createQueryBuilder();
		$latestGame = $qb ->select('sg')
			->from('AppBundle:SavedGames', 'sg')
			->setMaxResults(1)
			->orderBy('sg.id', 'DESC')
			->getQuery()
			->getOneOrNullResult()
		;
		if (isset($latestGame) && $latestGame->getStatus() == 'not_finished')
		{
			// Loading the old game
			$gameModel = new GameModel($this->get('session'));
			$game = $gameModel->loadSavedGame($latestGame->getGame());
		} else {
			$this->get('session')->clear();
			$places = [];
			$this->get('session')->set('places', $places);
			$this->get('app.model.game')->startGame();
			$game = $this->get('app.model.game')->getGame();
		}


		return $this->render(
			'AppBundle:Game:start.html.twig', array(
			'grid'          => $game->getBoard()->getGrid(),
			'currentPlayer' => $game->getCurrentPlayer(),
		));
	}

	/**
	 * @param $row
	 * @param $col
	 *
	 * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
	 */
	public function playAction($row, $col) {
		$session  = $this->get('session');
		$messages = [];
		$place    = '';
		// Getting the value from the array
		$row --;
		$col --;
		$game = $this->get('app.model.game')->getGame();

		if ( ! $game->isMoveLegal($row, $col)) {
			$messages [] = 'illegal move';
		} else {
			$place = $game->makeMove($row, $col);
			$this->get('app.model.game')->setGame($game);
			if ($this->isGameOver($game)) {
				return $this->redirectToRoute('end');
			}
		}
		$places = $session->get('places');
		if (isset($places)) {
			array_push($places, $place);
		}
		$session->set('places', $places);

		return $this->render(
			'AppBundle:Game:play.html.twig', array(
			'row'           => $row,
			'col'           => $col,
			'messages'      => $messages,
			'grid'          => $game->getBoard()->getGrid(),
			'currentPlayer' => $game->getCurrentPlayer(),
			'place'         => $place
		));
	}

	/**
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function endAction() {
		$qb = $this->getDoctrine()->getEntityManager()->createQueryBuilder();
		$latestGame = $qb ->select('sg')
		                  ->from('AppBundle:SavedGames', 'sg')
		                  ->setMaxResults(1)
		                  ->orderBy('sg.id', 'DESC')
		                  ->getQuery()
		                  ->getOneOrNullResult()
		;
		$game = $this->get('app.model.game')->getGame();

		if (Game::STATE_TIE == $game->getState()) {
			$message = 'Game Over: tie! how boring!';
		} else {
			$winningCells = $game->getWinningPositions($game->getBoard()->getGrid(), $game->getWinner());
			$message = 'Game Over: ' . $game->getWinner() . ' has won!';
		}
		$latestGame->setStatus('finished');
		$em = $this->getDoctrine()->getEntityManager();
		$em->persist($latestGame);
		$em->flush();

		return $this->render(
			'AppBundle:Game:end.html.twig', array(
			'message' => $message,
			'grid'    => $game->getBoard()->getGrid(),
			'winningCells' => $winningCells
		));
	}

	private function isGameOver(Game $game) {
		return in_array($game->getState(), array(Game::STATE_TIE, Game::STATE_WON));
	}

	/**
	 * @param $currentGame
	 * @param $status
	 *
	 * @return \Symfony\Component\HttpFoundation\RedirectResponse
	 */
	public function saveForLaterAction($currentGame, $status) {
		$em   = $this->getDoctrine()->getEntityManager();
		$game = new SavedGames();
		$serializedArray = serialize($currentGame);
		$game->setGame($serializedArray);
		$game->setStatus($status);
		$em->persist($game);
		$em->flush();

		$this->addFlash('message', 'Your game has been saved');

		return $this->redirectToRoute('default');
	}
}
