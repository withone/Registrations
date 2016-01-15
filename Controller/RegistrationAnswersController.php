<?php
/**
 * RegistrationAnswers Controller
 *
 * @author Noriko Arai <arai@nii.ac.jp>
 * @author Allcreator <info@allcreator.net>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 * @copyright Copyright 2014, NetCommons Project
 */

App::uses('AppController', 'Controller');

/**
 * RegistrationAnswersController
 *
 * @author Allcreator <info@allcreator.net>
 * @package NetCommons\Registrations\Controller
 */
class RegistrationAnswersController extends RegistrationsAppController {

/**
 * use model
 *
 * @var array
 */
	public $uses = array(
		'Registrations.RegistrationPage',
		'Registrations.RegistrationAnswerSummary',
		'Registrations.RegistrationAnswer',
	);

/**
 * use components
 *
 * @var array
 */
	public $components = array(
		'NetCommons.Permission',
		'Registrations.Registrations',
		'Registrations.RegistrationsOwnAnswer',
		'AuthorizationKeys.AuthorizationKey' => array(
			'operationType' => 'none',
			'targetAction' => 'view',
			'model' => 'Registration',
			'contentId' => 0),
		'VisualCaptcha.VisualCaptcha' => array(
			'operationType' => 'none',
			'targetAction' => 'view'),
	);

/**
 * use helpers
 *
 */
	public $helpers = [
		'NetCommons.Date',
		'Workflow.Workflow',
		'Registrations.RegistrationAnswer'
	];

/**
 * target registration data
 *
 */
	private $__registration = null;

/**
 * target isAbleToAnswer Action
 *
 */
	private $__ableToAnswerAction = ['view', 'confirm'];

/**
 * beforeFilter
 * NetCommonsお約束：できることならControllerのbeforeFilterで実行可/不可の判定して流れを変える
 *
 * @return void
 */
	public function beforeFilter() {
		// ゲストアクセスOKのアクションを設定
		$this->Auth->allow('view', 'confirm', 'thanks', 'no_more_answer');

		// 親クラスのbeforeFilterを済ませる
		parent::beforeFilter();

		// NetCommonsお約束：編集画面へのURLに編集対象のコンテンツキーが含まれている
		// まずは、そのキーを取り出す
		// 登録フォームキー
		$registrationKey = $this->_getRegistrationKeyFromPass();

		// キーで指定された登録フォームデータを取り出しておく
		$conditions = $this->Registration->getBaseCondition(
			array('Registration.key' => $registrationKey)
		);

		$this->__registration = $this->Registration->find('first', array(
			'conditions' => $conditions,
		));
		if (! $this->__registration) {
			// 単純に期間外のこともある
			$this->setAction('emptyRender');
			return;
			$this->setAction('throwBadRequest');
			return;
		}

		// 以下のisAbleto..の内部関数にてNetCommonsお約束である編集権限、参照権限チェックを済ませています
		// 閲覧可能か
		if (!$this->isAbleTo($this->__registration)) {
			// 不可能な時は「回答できません」画面を出すだけ
			$this->setAction('no_more_answer');
			return;
		}
		if (in_array($this->action, $this->__ableToAnswerAction)) {
			// 回答可能か
			if (!$this->isAbleToAnswer($this->__registration)) {
				// 回答が不可能な時は「回答できません」画面を出すだけ
				$this->setAction('no_more_answer');
				return;
			}
		}
		// 回答の初めのページであることが各種認証行う条件
		if (!$this->request->isPost() || !isset($this->request->data['RegistrationPage']['page_sequence'])) {
			// 認証キーコンポーネントお約束：
			// 取り出した登録フォームが認証キー確認を求めているなら、operationTypeをすり替える
			if ($this->__registration['Registration']['is_key_pass_use'] == RegistrationsComponent::USES_USE) {
				$this->AuthorizationKey->operationType = 'redirect';
				$this->AuthorizationKey->contentId = $this->__registration['Registration']['id'];
			}
			// 画像認証コンポーネントお約束：
			// 取り出した登録フォームが画像認証ありならば、operationTypeをすり替える
			if ($this->__registration['Registration']['is_image_authentication'] == RegistrationsComponent::USES_USE) {
				$this->VisualCaptcha->operationType = 'redirect';
			}
		}
	}
/**
 * test_mode
 *
 * テストモード回答のとき、一番最初に表示するページ
 * 一覧表示画面で「テスト」ボタンがここへ誘導するようになっている。
 * どのような登録フォームであるのかの各種属性設定をわかりやすくまとめて表示する表紙的な役割を果たす。
 *
 * あくまで作成者の便宜のために表示しているものであるので、最初のページだったら必ずここを表示といったような
 * 強制的redirectなどは設定しない。なので強制URL-Hackしたらこの画面をスキップすることだって可能。
 * 作成者への「便宜」のための親切心ページなのでスキップしたい人にはそうさせてあげるのでよいと考える。
 *
 * @return void
 */
	public function test_mode() {
		$status = $this->__registration['Registration']['status'];
		// テストモード確認画面からのPOSTや、現在の登録フォームデータのステータスが公開状態の時
		// 次へリダイレクト
		if ($this->request->isPost() || $status == WorkflowComponent::STATUS_PUBLISHED) {
			$this->redirect(NetCommonsUrl::actionUrl(array(
				'controller' => 'registration_answers',
				'action' => 'view',
				Current::read('Block.id'),
				$this->_getRegistrationKey($this->__registration),
				'frame_id' => Current::read('Frame.id')
			)));
			return;
		}
		$this->request->data['Frame'] = Current::read('Frame');
		$this->request->data['Block'] = Current::read('Block');
		$this->set('registration', $this->__registration);
	}

/**
 * view method
 * Display the question of the registration , to accept the answer input
 *
 * @return void
 */
	public function view() {
		$registration = $this->__registration;
		$registrationKey = $this->_getRegistrationKey($this->__registration);

		// 選択肢ランダム表示対応
		$this->__shuffleChoice($registration);

		// ページの指定のない場合はFIRST_PAGE_SEQUENCEをデフォルトとする
		$nextPageSeq = RegistrationsComponent::FIRST_PAGE_SEQUENCE;	// default

		// POSTチェック
		if ($this->request->isPost()) {
			// 回答データがある場合は回答をDBに書きこむ
			if (isset($this->data['RegistrationAnswer'])) {
				$summary = $this->RegistrationsOwnAnswer->forceGetProgressiveAnswerSummary($this->__registration);
				if (! $summary) {
					// 保存エラーの場合は今のページを再表示
					$nextPageSeq = $this->data['RegistrationPage']['page_sequence'];
				} else {
					if (! $this->RegistrationAnswer->saveAnswer($this->data, $registration, $summary)) {
						// 保存エラーの場合は今のページを再表示
						$nextPageSeq = $this->data['RegistrationPage']['page_sequence'];
					} else {
						// 回答データがあり、無事保存できたら次ページを取得する
						$nextPageSeq = $this->RegistrationPage->getNextPage(
							$registration,
							$this->data['RegistrationPage']['page_sequence'],
							$this->data['RegistrationAnswer']);
					}
				}
			}
			// 次ページはもう存在しない
			if ($nextPageSeq === false) {
				// 確認画面へ
				$url = NetCommonsUrl::actionUrl(array(
					'controller' => 'registration_answers',
					'action' => 'confirm',
					Current::read('Block.id'),
					$registrationKey,
					'frame_id' => Current::read('Frame.id'),
				));
				$this->redirect($url);
				return;
			}
		}
		if (! ($this->request->isPost() && $nextPageSeq == $this->data['RegistrationPage']['page_sequence'])) {
			$summary = $this->RegistrationsOwnAnswer->getProgressiveSummaryOfThisUser($registrationKey);
			$setAnswers = $this->RegistrationAnswer->getProgressiveAnswerOfThisSummary($summary);
			$this->set('answers', $setAnswers);
			$this->request->data['RegistrationAnswer'] = $setAnswers;

			// 入力される回答データですがsetで設定するデータとして扱います
			// 誠にCake流儀でなくて申し訳ないのですが、様々な種別のAnswerデータを
			// 特殊な文字列加工して統一化した形状でDBに入れている都合上、このような仕儀になっています
		} else {
			$this->set('answers', $this->request->data['RegistrationAnswer']);
		}

		// 質問情報をView変数にセット
		$this->request->data['Frame'] = Current::read('Frame');
		$this->request->data['Block'] = Current::read('Block');
		$this->request->data['RegistrationPage'] = $registration['RegistrationPage'][$nextPageSeq];
		$this->set('registration', $registration);
		$this->set('questionPage', $registration['RegistrationPage'][$nextPageSeq]);
		$this->NetCommons->handleValidationError($this->RegistrationAnswer->validationErrors);
	}
/**
 * confirm method
 *
 * @return void
 */
	public function confirm() {
		// 解答入力画面で表示していたときのシャッフルを取り出す
		$this->__shuffleChoice($this->__registration);

		// 回答中サマリレコード取得
		$summary = $this->RegistrationsOwnAnswer->getProgressiveSummaryOfThisUser(
			$this->_getRegistrationKey($this->__registration));
		if (!$summary) {
			$this->setAction('throwBadRequest');
			return;
		}

		// POSTチェック
		if ($this->request->isPost()) {
			// サマリの状態を完了にして確定する
			$summary['RegistrationAnswerSummary']['answer_status'] = RegistrationsComponent::ACTION_ACT;
			$summary['RegistrationAnswerSummary']['answer_time'] = (new NetCommonsTime())->getNowDatetime();
			$this->RegistrationAnswerSummary->save($summary['RegistrationAnswerSummary']);
			$this->RegistrationsOwnAnswer->saveOwnAnsweredKeys($this->_getRegistrationKey($this->__registration));

			// ありがとう画面へ行く
			$url = NetCommonsUrl::actionUrl(array(
				'controller' => 'registration_answers',
				'action' => 'thanks',
				Current::read('Block.id'),
				$this->_getRegistrationKey($this->__registration),
				'frame_id' => Current::read('Frame.id'),
			));
			$this->redirect($url);
		}

		// 回答情報取得
		// 回答情報並べ替え
		$setAnswers = $this->RegistrationAnswer->getProgressiveAnswerOfThisSummary($summary);

		// 質問情報をView変数にセット
		$this->request->data['Frame'] = Current::read('Frame');
		$this->request->data['Block'] = Current::read('Block');
		$this->set('registration', $this->__registration);
		$this->request->data['RegistrationAnswer'] = $setAnswers;
		$this->set('answers', $setAnswers);
	}
/**
 * thanks method
 *
 * @return void
 */
	public function thanks() {
		// 後始末
		// 回答中にたまっていたセッションキャッシュをクリア
		$this->Session->delete('Registrations.' . $this->__registration['Registration']['key']);

		// View変数にセット
		$this->request->data['Frame'] = Current::read('Frame');
		$this->request->data['Block'] = Current::read('Block');
		$this->set('registration', $this->__registration);
		$this->set('ownAnsweredKeys', $this->RegistrationsOwnAnswer->getOwnAnsweredKeys());
	}
/**
 * no_more_answer method
 * 条件によって回答できない登録フォームにアクセスしたときに表示
 *
 * @return void
 */
	public function no_more_answer() {
	}
/**
 * _shuffleChoice
 * shuffled choices and write into session
 *
 * @param array &$registration 登録フォーム
 * @return void
 */
	private function __shuffleChoice(&$registration) {
		foreach ($registration['RegistrationPage'] as &$page) {
			foreach ($page['RegistrationQuestion'] as &$q) {
				$choices = $q['RegistrationChoice'];
				if ($q['is_choice_random'] == RegistrationsComponent::USES_USE) {
					$sessionPath = 'Registrations.' . $registration['Registration']['key'] . '.RegistrationQuestion.' . $q['key'] . '.RegistrationChoice';
					if ($this->Session->check($sessionPath)) {
						$choices = $this->Session->read($sessionPath);
					} else {
						shuffle($choices);
						$this->Session->write($sessionPath, $choices);
					}
				}
				$q['RegistrationChoice'] = $choices;
			}
		}
	}
}
