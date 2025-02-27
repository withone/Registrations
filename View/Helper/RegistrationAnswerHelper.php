<?php
/**
 * RegistrationAnswer Helper
 *
 * @author Noriko Arai <arai@nii.ac.jp>
 * @author Allcreator Co., Ltd. <info@allcreator.net>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 * @copyright Copyright 2014, NetCommons Project
 */
App::uses('AppHelper', 'View/Helper');
App::uses('NetCommonsTime', 'NetCommons.Utility');

/**
 * Registrations Answer Helper
 *
 * @author Allcreator Co., Ltd. <info@allcreator.net>
 * @package NetCommons\Registrations\View\Helper
 */
class RegistrationAnswerHelper extends AppHelper {

/**
 * Other helpers used by FormHelper
 *
 * @var array
 */
	public $helpers = array(
		'NetCommons.NetCommonsForm',
		'Form'
	);

/**
 * Answer html create by question type
 *
 * @var array
 */
	protected $_answerFunc = array(
		RegistrationsComponent::TYPE_SELECTION => 'singleChoice',
		RegistrationsComponent::TYPE_MULTIPLE_SELECTION => 'multipleChoice',
		RegistrationsComponent::TYPE_TEXT => 'singleText',
		RegistrationsComponent::TYPE_TEXT_AREA => 'textArea',
		RegistrationsComponent::TYPE_MATRIX_SELECTION_LIST => 'matrix',
		RegistrationsComponent::TYPE_MATRIX_MULTIPLE => 'matrix',
		RegistrationsComponent::TYPE_SINGLE_SELECT_BOX => 'singleList',
		RegistrationsComponent::TYPE_DATE_AND_TIME => 'dateTimeInput',
		RegistrationsComponent::TYPE_EMAIL => 'emailInput',
		RegistrationsComponent::TYPE_FILE => 'fileInput'
	);

/**
 * 登録作成
 *
 * @param array $question 項目データ
 * @param bool $readonly 読み取り専用
 * @return string 登録HTML
 * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
 */
	public function answer($question, $readonly = false) {
		// 項目セットをもらう
		// 種別に応じて項目＆登録の要素を作成し返す
		$index = $question['key'];
		$baseFieldName = 'RegistrationAnswer.' . $index . '.0.';
		$fieldName = $baseFieldName . 'answer_value';

		$ret = call_user_func_array(
			array($this, $this->_answerFunc[$question['question_type']]),
			array($index, $fieldName, $question, $readonly));

		if (! RegistrationsComponent::isMatrixInputType($question['question_type'])) {
			$ret .= $this->_error($fieldName);
			$ret .= $this->NetCommonsForm->hidden($baseFieldName . 'registration_answer_summary_id');
			$ret .= $this->NetCommonsForm->hidden($baseFieldName . 'registration_question_key',
				array('value' => $index));
			$ret .= $this->NetCommonsForm->hidden($baseFieldName . 'id');
			$ret .= $this->NetCommonsForm->hidden($baseFieldName . 'matrix_choice_key',
				array('value' => null));
		}
		return $ret;
	}
/**
 * 択一選択登録作成
 *
 * @param string $index 登録データのPOST用dataのインデックス値
 * @param string $fieldName フィールド名
 * @param array $question 項目データ
 * @param bool $readonly 読み取り専用
 * @return string 択一選択肢登録のHTML
 */
	public function singleChoice($index, $fieldName, $question, $readonly) {
		$ret = '';
		$otherAnswerFieldName = 'RegistrationAnswer.' . $index . '.0.other_answer_value';

		if (isset($question['RegistrationChoice'])) {
			$afterLabel = false;
			$choices = Hash::sort($question['RegistrationChoice'], '{n}.other_choice_type', 'asc');
			$options = $this->_getChoiceOptionElement($choices);
			$options = array_map('h', $options); // escape
			$otherChoice = Hash::extract($question['RegistrationChoice'],
				'{n}[other_choice_type!=' . RegistrationsComponent::OTHER_CHOICE_TYPE_NO_OTHER_FILED . ']');
			if ($otherChoice) {
				$otherInput = $this->NetCommonsForm->input($otherAnswerFieldName, array(
					'type' => 'text',
					'label' => false,
					'div' => false,
					'disabled' => $readonly,
					'error' => false,
				));
				$afterLabel = $otherInput;
			}

			$inline = false;
			if ($question['is_choice_horizon'] == RegistrationsComponent::USES_USE) {
				$inline = true;
			}
			// 下のような形でradioをつくるとHiddenが自動的には付随されなかった！
			// 仕方ないので意図的に作成している
			$ret = $this->NetCommonsForm->hidden($fieldName, array('value' => ''));
			$ret .= $this->NetCommonsForm->input($fieldName, array(
				'type' => 'radio',
				'options' => $options,
				'legend' => false,
				'label' => false,
				'div' => false,
				'inline' => $inline,
				'after' => $afterLabel,
				'disabled' => $readonly,
				'error' => false,
				'hiddenField' => false,
			));
		}
		return $ret;
	}

/**
 * 複数選択登録作成
 *
 * @param string $index 登録データのPOST用dataのインデックス値
 * @param string $fieldName フィールド名
 * @param array $question 項目データ
 * @param bool $readonly 読み取り専用
 * @return string 複数選択肢登録のHTML
 */
	public function multipleChoice($index, $fieldName, $question, $readonly) {
		$ret = '';
		$otherAnswerFieldName = 'RegistrationAnswer.' . $index . '.0.other_answer_value';

		if (isset($question['RegistrationChoice'])) {
			$afterLabel = '';
			$choices = Hash::sort($question['RegistrationChoice'], '{n}.other_choice_type', 'asc');
			$options = $this->_getChoiceOptionElement($choices);

			$otherChoice = Hash::extract($question['RegistrationChoice'],
				'{n}[other_choice_type!=' . RegistrationsComponent::OTHER_CHOICE_TYPE_NO_OTHER_FILED . ']');
			if ($otherChoice) {
				$otherInput = $this->NetCommonsForm->input($otherAnswerFieldName, array(
					'type' => 'text',
					'label' => false,
					'div' => false,
					'disabled' => $readonly,
					'error' => false,
				));
				$afterLabel = '<div class="checkbox-inline">' . $otherInput . '</div>';
			}

			$checkboxClass = 'checkbox';
			if ($question['is_choice_horizon'] == RegistrationsComponent::USES_USE) {
				$checkboxClass = 'checkbox-inline';
			}
			$ret .= $this->NetCommonsForm->input($fieldName, array(
				'type' => 'select',
				'multiple' => 'checkbox',
				'options' => $options,
				'label' => false,
				'div' => 'form-input-outer',
				'class' => $checkboxClass . ' nc-checkbox',
				'disabled' => $readonly,
				'hiddenField' => !$readonly,
				'error' => false,
			));

			$ret .= $afterLabel;
		}
		return $ret;
	}
/**
 * テキスト登録作成
 *
 * @param string $index  登録データのPOST用dataのインデックス値
 * @param string $fieldName フィールド名
 * @param array $question  項目データ
 * @param bool $readonly 読み取り専用
 * @return string 複数選択肢登録のHTML
 */
	public function singleText($index, $fieldName, $question, $readonly) {
		if ($readonly) {
			$ret = h($this->value($fieldName));
			return $ret;
		}
		$ret = $this->NetCommonsForm->input($fieldName, array(
			//'div' => 'form-inline',
			'type' => 'text',
			'label' => false,
			'error' => false,
			));
		if ($question['is_range'] == RegistrationsComponent::USES_USE) {
			$ret .= '<span class="help-block">';
			if ($question['question_type_option'] == RegistrationsComponent::TYPE_OPTION_NUMERIC) {
				$ret .= sprintf(
					__d('registrations', 'Please enter a number between %s and %s'),
					$question['min'],
					$question['max']);
			} else {
				$ret .= sprintf(
					__d('registrations', 'Please enter between %s letters and %s letters'),
					$question['min'],
					$question['max']);
			}
			$ret .= '</span>';
		}
		return $ret;
	}
/**
 * 長文テキスト登録作成
 *
 * @param string $index 登録データのPOST用dataのインデックス値
 * @param string $fieldName フィールド名
 * @param array $question 項目データ
 * @param bool $readonly 読み取り専用
 * @return string HTML
 */
	public function textArea($index, $fieldName, $question, $readonly) {
		if ($readonly) {
			$ret = nl2br(h($this->value($fieldName)));
			return $ret;
		}
		$ret = $this->NetCommonsForm->textarea($fieldName, array(
			'div' => 'form-inline',
			'label' => false,
			'class' => 'form-control',
			'rows' => 5,
			'error' => false,
		));
		return $ret;
	}
/**
 * リストボックス登録作成
 *
 * @param string $index 登録データのPOST用dataのインデックス値
 * @param string $fieldName フィールド名
 * @param array $question 項目データ
 * @param bool $readonly 読み取り専用
 * @return string 複数選択肢登録のHTML
 */
	public function singleList($index, $fieldName, $question, $readonly) {
		if ($readonly) {
			$answer = $this->value($fieldName);
			$ret = substr($answer, strrpos($answer, RegistrationsComponent::ANSWER_VALUE_DELIMITER) + 1);
			return $ret;
		}
		if (isset($question['RegistrationChoice'])) {
			$options = $this->_getChoiceOptionElement($question['RegistrationChoice']);
			$ret = $this->NetCommonsForm->input($fieldName, array(
				'type' => 'select',
				'options' => $options,
				'label' => false,
				'div' => 'form-inline',
				'disabled' => $readonly,
				'empty' => __d('registrations', 'Please choose one'),
				'error' => false,
			));
		}
		return $ret;
	}
/**
 * マトリクス登録作成
 *
 * @param string $index  登録データのPOST用dataのインデックス値
 * @param string $fieldName フィールド名
 * @param array $question  項目データ
 * @param bool $readonly 読み取り専用
 * @return string 複数選択肢登録のHTML
 */
	public function matrix($index, $fieldName, $question, $readonly) {
		if (isset($question['RegistrationChoice'])) {
			$cols = Hash::extract($question['RegistrationChoice'],
				'{n}[matrix_type=' . RegistrationsComponent::MATRIX_TYPE_COLUMN . ']');
			$rowChoices = Hash::extract($question['RegistrationChoice'],
				'{n}[matrix_type!=' . RegistrationsComponent::MATRIX_TYPE_COLUMN . ']');
			$options = $this->_getChoiceOptionElement($cols);
		}
		$addClass = '';
		if (! $readonly) {
			$addClass = ' table-striped table-hover ';
		}
		$errorMessage = '';

		$ret = '<table class="table ';
		$ret .= $addClass;
		$ret .= 'table-bordered text-center registration-matrix-table">';
		$ret .= '<thead><tr><th></th>';
		foreach ($options as $opt) {
			$ret .= '<th class="text-center">' . $opt . '</th>';
		}
		$ret .= '</thead><tbody>';

		foreach ($rowChoices as $rowIndex => $row) {
			$baseFieldName = 'RegistrationAnswer.' . $index . '.' . $rowIndex;
			$ret .= '<tr><th>' . $row['choice_label'];
			$ret .= $this->NetCommonsForm->hidden(	$baseFieldName . '.registration_answer_summary_id');
			$ret .= $this->NetCommonsForm->hidden(	$baseFieldName . '.registration_question_key',
				array('value' => $index));
			$ret .= $this->NetCommonsForm->hidden(	$baseFieldName . '.matrix_choice_key',
				array('value' => $row['key']));
			$ret .= $this->NetCommonsForm->hidden(	$baseFieldName . '.id');
			if ($row['other_choice_type'] != RegistrationsComponent::OTHER_CHOICE_TYPE_NO_OTHER_FILED) {
				$ret .= $this->NetCommonsForm->input($baseFieldName . '.other_answer_value', array(
					'type' => 'text',
					'label' => false,
					'div' => false,
					'disabled' => $readonly,
				));
			}
			$ret .= '</th>';
			$ret .= $this->_getMatrixRow(
				$question['question_type'],
				$baseFieldName . '.answer_value',
				$options,
				$readonly);
			$ret .= '</tr>';
			$errorMessage .= $this->_error($baseFieldName . '.answer_value');
		}
		$ret .= '</tbody></table>';
		$ret .= $errorMessage;
		return $ret;
	}
/**
 * 日付・時間登録作成
 *
 * @param string $index 登録データのPOST用dataのインデックス値
 * @param string $fieldName フィールド名
 * @param array $question 項目データ
 * @param bool $readonly 読み取り専用
 * @return string 複数選択肢登録のHTML
 */
	public function dateTimeInput($index, $fieldName, $question, $readonly) {
		if ($readonly) {
			$ret = $this->value($fieldName);
			return $ret;
		}

		$rangeMessage = '<span class="help-block">';
		$options = array();
		if ($question['question_type_option'] == RegistrationsComponent::TYPE_OPTION_DATE) {
			$icon = 'glyphicon-calendar';
			$options['format'] = 'YYYY-MM-DD';
			if ($question['is_range'] == RegistrationsComponent::USES_USE) {
				$options['minDate'] = $question['min'];
				$options['maxDate'] = $question['max'];
				$rangeMessage .= sprintf(__d('registrations', 'Please enter at %s to %s'),
					date('Y-m-d', strtotime($question['min'])),
					date('Y-m-d', strtotime($question['max'])));
			}
		} elseif ($question['question_type_option'] == RegistrationsComponent::TYPE_OPTION_TIME) {
			$icon = 'glyphicon-time';
			$options['format'] = 'HH:mm';
			if ($question['is_range'] == RegistrationsComponent::USES_USE) {
				$tm = new NetCommonsTime();
				$options['minDate'] = date('Y-m-d ', strtotime($tm->getNowDatetime())) . $question['min'];
				$options['maxDate'] = date('Y-m-d ', strtotime($tm->getNowDatetime())) . $question['max'];
				$rangeMessage .= sprintf(__d('registrations', 'Please enter at %s to %s'),
					date('H:i', strtotime($question['min'])),
					date('H:i', strtotime($question['max'])));
			}
		} elseif ($question['question_type_option'] == RegistrationsComponent::TYPE_OPTION_DATE_TIME) {
			$icon = 'glyphicon-calendar';
			$options['format'] = 'YYYY-MM-DD HH:mm';
			if ($question['is_range'] == RegistrationsComponent::USES_USE) {
				$options['minDate'] = $question['min'];
				$options['maxDate'] = $question['max'];
				$rangeMessage .= sprintf(__d('registrations', 'Please enter at %s to %s'),
					date('Y-m-d H:i', strtotime($question['min'])),
					date('Y-m-d H:i', strtotime($question['max'])));
			}
		}
		$options = json_encode($options);
		$rangeMessage .= '</span>';

		$ret = '<div class="row"><div class="col-sm-4">';
		$ret .= '<div class="date" >';
		$ret .= $this->NetCommonsForm->input($fieldName,
							array('type' => 'datetime',
								'div' => false,
								'class' => 'form-control',
								'datetimepicker' => 1,
								'convert_timezone' => false,
								'datetimepicker-options' => $options,
								'ng-model' => 'dateAnswer[' . "'" . $question['key'] . "'" . ']',
								//'value' => $this->value($fieldName),
								'label' => false,
								'error' => false));
		$ret .= '</div>';
		$ret .= '</div></div>';
		$ret .= $rangeMessage;
		return $ret;
	}

/**
 * メールアドレス
 *
 * @param string $index 登録データのPOST用dataのインデックス値
 * @param string $fieldName フィールド名
 * @param array $question 項目データ
 * @param bool $readonly 読み取り専用
 * @return string Html
 */
	public function emailInput($index, $fieldName, $question, $readonly) {
		if ($readonly) {
			$ret = h(nl2br($this->value($fieldName)));
			return $ret;
		}
		$ret = $this->NetCommonsForm->email($fieldName, array(
			//'div' => 'form-inline',
			'type' => 'text',
			'label' => false,
			'error' => false,
			'again' => true,
		));
		return $ret;
	}

/**
 * ファイル添付
 *
 * @param string $index 登録データのPOST用dataのインデックス値
 * @param string $fieldName フィールド名
 * @param array $question 項目データ
 * @param bool $readonly 読み取り専用
 * @return string Html
 */
	public function fileInput($index, $fieldName, $question, $readonly) {
		if ($readonly) {
			$ret = h(nl2br($this->value($fieldName)));
			return $ret;
		}
		$fileFieldName = $fieldName . '_file';
		$ret = $this->NetCommonsForm->uploadFile($fileFieldName, array(
			'div' => 'form-inline',
			'type' => 'text',
			'label' => false,
			'error' => false,
		));
		//$ret .= '<div class="form-group">';
		//$ret .= '<label class="control-label" stseyle="margin-top: 5px;">
		//			メールアドレス(確認用)				</label>';
		//$ret .= $this->NetCommonsForm->input($fieldName . '_confirm', array(
		//	'div' => 'form-inline',
		//	'type' => 'text',
		//	'label' => false,
		//	'error' => false,
		//	//'after' => '（確認用）'
		//));
		//$ret .='</div>';
		return $ret;
	}

/**
 * エラーメッセージ表示要素作成
 *
 * @param string $fieldName フィールド名
 * @return string エラーメッセージ表示要素のHTML
 */
	protected function _error($fieldName) {
		$output = $this->NetCommonsForm->error($fieldName, null, array('class' => 'help-block'));
		return $output;
	}

/**
 * 選択肢要素作成
 *
 * @param array $choices 選択肢データ
 * @return string 選択肢要素のHTML
 */
	protected function _getChoiceOptionElement($choices) {
		$ret = array();
		foreach ($choices as $choice) {
			$choiceIndex = sprintf('%s%s%s%s',
				RegistrationsComponent::ANSWER_DELIMITER,
				$choice['key'],
				RegistrationsComponent::ANSWER_VALUE_DELIMITER,
				$choice['choice_label']);
			$ret[$choiceIndex] = $choice['choice_label'];
		}
		return $ret;
	}
/**
 * マトリクス選択肢要素作成
 *
 * @param int $questionType 項目種別
 * @param string $fieldName フィールド名
 * @param array $options 選択肢データ
 * @param bool $readonly 読み取り専用
 * @return string マトリクス選択行のHTML
 */
	protected function _getMatrixRow($questionType, $fieldName, $options, $readonly) {
		$ret = '';
		$optCount = 0;
		$keys = array_keys($options);
		foreach ($keys as $key) {
			$ret .= '<td>';
			$inputOptions = array(
				'options' => array($key => ''),
				'label' => false,
				'div' => false,
				'class' => '',
				'hiddenField' => ($readonly || $optCount != 0) ? false : true,
				'disabled' => $readonly,
				'error' => false,
			);
			if ($questionType == RegistrationsComponent::TYPE_MATRIX_SELECTION_LIST) {
				$inputOptions['type'] = 'radio';
			} else {
				$inputOptions['type'] = 'select';
				$inputOptions['multiple'] = 'checkbox';
			}
			$ret .= $this->NetCommonsForm->input($fieldName, $inputOptions);
			$optCount++;
			$ret .= '</td>';
		}
		return $ret;
	}
}
