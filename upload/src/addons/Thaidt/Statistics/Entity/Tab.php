<?php

namespace Thaidt\Statistics\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $tab_id
 * @property string $title
 * @property string $tab_type
 * @property array $node_ids
 * @property int $display_order
 * @property bool $active
 *
 * GETTERS
 * @property \XF\Phrase $type_title
 */
class Tab extends Entity
{
	public function getTypeTitle()
	{
		return \XF::phrase('thaidt_statistics_type_' . $this->tab_type);
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_thaidt_statistics_tab';
		$structure->shortName = 'Thaidt\Statistics:Tab';
		$structure->primaryKey = 'tab_id';
		$structure->columns = [
			'tab_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'title' => ['type' => self::STR, 'required' => true, 'maxLength' => 100],
			'tab_type' => ['type' => self::STR, 'required' => true, 'default' => 'recent_replies'],
			'node_ids' => ['type' => self::JSON_ARRAY, 'default' => []],
			'display_order' => ['type' => self::UINT, 'default' => 10],
			'active' => ['type' => self::BOOL, 'default' => true],
		];
		$structure->getters = [
			'type_title' => true,
		];
		$structure->relations = [];

		return $structure;
	}
}
