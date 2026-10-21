<?php

namespace Thaidt\Statistics\Option;

use XF\Entity\Option;
use XF\Option\AbstractOption;
use XF\Repository\NodeRepository;

use function is_array, trim, array_values, count, array_unique;

class TabManager extends AbstractOption
{
	public static function renderTabManager(Option $option, array $htmlParams)
	{
		/** @var NodeRepository $nodeRepo */
		$nodeRepo = \XF::repository(NodeRepository::class);
		$nodeOptions = $nodeRepo->getNodeOptionsData(true, null, 'option');

		$value = $option->option_value;

		if ($value === null || !is_array($value))
		{
			$value = [
				[
					'id' => 'recent_replies',
					'title' => 'Vừa trả lời',
					'type' => 'recent_replies',
					'node_ids' => [],
					'display_order' => 10,
					'active' => 1,
				],
				[
					'id' => 'latest_threads',
					'title' => 'Thành viên mới đăng',
					'type' => 'latest_threads',
					'node_ids' => [],
					'display_order' => 20,
					'active' => 1,
				],
				[
					'id' => 'jobs',
					'title' => 'Việc làm',
					'type' => 'forum_nodes',
					'node_ids' => [],
					'display_order' => 30,
					'active' => 1,
				],
				[
					'id' => 'new_members',
					'title' => 'Vừa gia nhập',
					'type' => 'new_members',
					'node_ids' => [],
					'display_order' => 40,
					'active' => 1,
				],
			];
		}

		$inputName = !empty($htmlParams['inputName']) ? $htmlParams['inputName'] : 'options[thaidt_statistics_tabs]';

		return static::getTemplate('admin:option_thaidt_statistics_tab_manager', $option, $htmlParams, [
			'tabs' => $value,
			'nodeOptions' => $nodeOptions,
			'inputName' => $inputName,
			'htmlParams' => $htmlParams,
		]);
	}

	public static function verifyOption(&$value, \XF\Entity\Option $option, $optionId)
	{
		if (!is_array($value))
		{
			$value = [];
			return true;
		}

		$output = [];
		foreach ($value as $tab)
		{
			if (!is_array($tab))
			{
				continue;
			}

			// Skip if explicitly marked for deletion
			if (!empty($tab['_delete']))
			{
				continue;
			}

			$type = !empty($tab['type']) ? trim($tab['type']) : 'recent_replies';
			$title = isset($tab['title']) ? trim($tab['title']) : '';

			// Auto assign default title if blank
			if ($title === '')
			{
				switch ($type)
				{
					case 'recent_replies': $title = 'Vừa trả lời'; break;
					case 'latest_threads': $title = 'Bài mới tạo'; break;
					case 'most_viewed': $title = 'Xem nhiều nhất'; break;
					case 'sticky': $title = 'Chủ đề ghim'; break;
					case 'new_members': $title = 'Vừa gia nhập'; break;
					case 'forum_nodes': $title = 'Diễn đàn chọn lọc'; break;
					default: $title = 'Tab Thống kê'; break;
				}
			}

			$nodeIds = [];
			if (!empty($tab['node_ids']) && is_array($tab['node_ids']))
			{
				foreach ($tab['node_ids'] as $nId)
				{
					$nId = (int)$nId;
					if ($nId >= 0)
					{
						$nodeIds[] = $nId;
					}
				}
			}

			$output[] = [
				'id' => !empty($tab['id']) ? trim($tab['id']) : 'tab_' . (count($output) + 1),
				'title' => $title,
				'type' => $type,
				'node_ids' => array_values(array_unique($nodeIds)),
				'display_order' => isset($tab['display_order']) ? (int)$tab['display_order'] : ((count($output) + 1) * 10),
				'active' => !empty($tab['active']) ? 1 : 0,
			];
		}

		$value = array_values($output);
		return true;
	}
}
