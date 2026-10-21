<?php

namespace Thaidt\Statistics\Widget;

use XF\Entity\Thread;
use XF\Entity\User;
use XF\Http\Request;
use XF\Repository\NodeRepository;
use XF\Repository\ThreadRepository;
use XF\Widget\AbstractWidget;

use function in_array, is_array, preg_split, array_filter, array_map, trim, array_unique, array_diff, array_values, usort, max;

class RecentReplies extends AbstractWidget
{
	protected $defaultOptions = [
		'limit' => 0,
		'node_ids' => [],
		'exclude_thread_ids' => '',
		'style' => 'table',
	];

	protected function getDefaultTemplateParams($context)
	{
		$params = parent::getDefaultTemplateParams($context);
		if ($context == 'options')
		{
			$nodeRepo = $this->app->repository(NodeRepository::class);
			$params['nodeTree'] = $nodeRepo->createNodeTree($nodeRepo->getFullNodeList());
		}
		return $params;
	}

	public function render()
	{
		$visitor = \XF::visitor();
		$options = \XF::options();

		$widgetOptions = $this->options;

		// 1. Determine Limit
		$limit = (int)($widgetOptions['limit'] ?: ($options->thaidt_statistics_limit ?? 10));
		if ($limit < 1)
		{
			$limit = 10;
		}

		// 2. Determine Node Exclusions (Global & Widget Options)
		$excludeNodeIds = [];
		if (!empty($widgetOptions['node_ids']) && is_array($widgetOptions['node_ids']) && !in_array(0, $widgetOptions['node_ids']))
		{
			$excludeNodeIds = array_map('intval', $widgetOptions['node_ids']);
		}
		else if (!empty($options->thaidt_statistics_exclude_nodes) && is_array($options->thaidt_statistics_exclude_nodes))
		{
			$excludeNodeIds = array_map('intval', $options->thaidt_statistics_exclude_nodes);
		}

		// Also resolve child nodes of excluded nodes
		if (!empty($excludeNodeIds))
		{
			/** @var NodeRepository $nodeRepo */
			$nodeRepo = $this->repository(NodeRepository::class);
			$nodeList = $nodeRepo->getFullNodeList();
			$nodeTree = $nodeRepo->createNodeTree($nodeList);

			$allExcludeNodeIds = [];
			foreach ($excludeNodeIds as $exId)
			{
				if ($exId > 0)
				{
					$allExcludeNodeIds[] = $exId;
					$descendants = $nodeTree->getDescendants($exId);
					if ($descendants)
					{
						foreach ($descendants as $descId => $descRecord)
						{
							$allExcludeNodeIds[] = (int)$descId;
						}
					}
				}
			}
			$excludeNodeIds = array_values(array_unique($allExcludeNodeIds));
		}

		// 3. Determine Thread Exclusions
		$excludeThreadIdsRaw = !empty($widgetOptions['exclude_thread_ids'])
			? $widgetOptions['exclude_thread_ids']
			: ($options->thaidt_statistics_exclude_threads ?? '');
		$excludeThreadIds = $this->parseThreadIds($excludeThreadIdsRaw);

		// 4. Header title & visibility settings
		$showHeader = isset($options->thaidt_statistics_show_header) ? (bool)$options->thaidt_statistics_show_header : true;
		$headerTitle = !empty($options->thaidt_statistics_header_title) ? $options->thaidt_statistics_header_title : \XF::phrase('thaidt_statistics_title');

		// 5. Fetch Active Tabs from Dynamic Option Manager array
		$style = $widgetOptions['style'] ?? 'table';
		$router = $this->app->router('public');

		$tabsConfig = $options->thaidt_statistics_tabs ?? null;
		$tabsData = [];

		if (is_array($tabsConfig) && !empty($tabsConfig))
		{
			usort($tabsConfig, function($a, $b) {
				return ((int)($a['display_order'] ?? 10)) <=> ((int)($b['display_order'] ?? 10));
			});

			foreach ($tabsConfig as $i => $tab)
			{
				if (empty($tab['active']))
				{
					continue;
				}

				$tabId = !empty($tab['id']) ? $tab['id'] : 'tab_' . $i;
				$tabTitle = !empty($tab['title']) ? $tab['title'] : 'Tab ' . ($i + 1);
				$type = !empty($tab['type']) ? $tab['type'] : 'recent_replies';
				$nodes = !empty($tab['node_ids']) && is_array($tab['node_ids']) ? $tab['node_ids'] : [];

				switch ($type)
				{
					case 'recent_replies':
						$tabsData[$tabId] = [
							'id' => $tabId,
							'title' => $tabTitle,
							'tab_type' => 'recent_replies',
							'threads' => $this->getThreads('last_post_date', $limit, $excludeNodeIds, $excludeThreadIds, $visitor),
							'type' => 'threads',
						];
						break;

					case 'latest_threads':
						$tabsData[$tabId] = [
							'id' => $tabId,
							'title' => $tabTitle,
							'tab_type' => 'latest_threads',
							'threads' => $this->getThreads('post_date', $limit, $excludeNodeIds, $excludeThreadIds, $visitor),
							'type' => 'threads',
						];
						break;

					case 'most_viewed':
						$tabsData[$tabId] = [
							'id' => $tabId,
							'title' => $tabTitle,
							'tab_type' => 'most_viewed',
							'threads' => $this->getThreads('view_count', $limit, $excludeNodeIds, $excludeThreadIds, $visitor),
							'type' => 'threads',
						];
						break;

					case 'sticky':
						$tabsData[$tabId] = [
							'id' => $tabId,
							'title' => $tabTitle,
							'tab_type' => 'sticky',
							'threads' => $this->getThreads('sticky', $limit, $excludeNodeIds, $excludeThreadIds, $visitor),
							'type' => 'threads',
						];
						break;

					case 'new_members':
						$tabsData[$tabId] = [
							'id' => $tabId,
							'title' => $tabTitle,
							'tab_type' => 'new_members',
							'users' => $this->getNewMembers($limit, $visitor),
							'type' => 'users',
						];
						break;

					case 'forum_nodes':
						$tabsData[$tabId] = [
							'id' => $tabId,
							'title' => $tabTitle,
							'tab_type' => 'forum_nodes',
							'threads' => $this->getThreads('last_post_date', $limit, $excludeNodeIds, $excludeThreadIds, $visitor, $nodes),
							'type' => 'threads',
						];
						break;
				}
			}
		}

		// Fallback to recent_replies if no active tabs
		if (empty($tabsData))
		{
			$tabsData['recent_replies'] = [
				'id' => 'recent_replies',
				'title' => \XF::phrase('thaidt_statistics_tab_recent_replies'),
				'tab_type' => 'recent_replies',
				'threads' => $this->getThreads('last_post_date', $limit, $excludeNodeIds, $excludeThreadIds, $visitor),
				'type' => 'threads',
			];
		}

		$viewParams = [
			'title' => $this->getTitle() ?: $headerTitle,
			'showHeader' => $showHeader,
			'link' => $router->buildLink('whats-new/posts'),
			'tabsData' => $tabsData,
			'style' => $style,
		];

		return $this->renderer('widget_thaidt_recent_replies', $viewParams);
	}

	protected function getThreads($orderType, $limit, array $excludeNodeIds, array $excludeThreadIds, $visitor, array $includeNodeIds = [])
	{
		/** @var \XF\Finder\ThreadFinder $threadFinder */
		$threadFinder = $this->finder(\XF\Finder\ThreadFinder::class);
		$threadFinder
			->with(['Forum', 'User', 'LastPoster'])
			->where('discussion_state', 'visible')
			->where('discussion_type', '<>', 'redirect')
			->with('Forum.Node.Permissions|' . $visitor->permission_combination_id)
			->limit(max($limit * 3, 30));

		// 1. Resolve Include Nodes
		$validIncludeNodes = [];
		if (!empty($includeNodeIds))
		{
			foreach ($includeNodeIds as $nId)
			{
				$nId = (int)$nId;
				if ($nId > 0)
				{
					$validIncludeNodes[] = $nId;
				}
			}
		}

		if (!empty($validIncludeNodes) && !in_array(0, $validIncludeNodes))
		{
			/** @var NodeRepository $nodeRepo */
			$nodeRepo = $this->repository(NodeRepository::class);
			$nodeList = $nodeRepo->getFullNodeList();
			$nodeTree = $nodeRepo->createNodeTree($nodeList);

			$targetNodeIds = [];
			foreach ($validIncludeNodes as $nId)
			{
				$targetNodeIds[] = $nId;
				$descendants = $nodeTree->getDescendants($nId);
				if ($descendants)
				{
					foreach ($descendants as $childId => $childRecord)
					{
						$targetNodeIds[] = (int)$childId;
					}
				}
			}

			$targetNodeIds = array_values(array_unique($targetNodeIds));

			// Remove any excluded nodes from the include list
			if (!empty($excludeNodeIds))
			{
				$targetNodeIds = array_values(array_diff($targetNodeIds, $excludeNodeIds));
			}

			if (!empty($targetNodeIds))
			{
				$threadFinder->where('node_id', $targetNodeIds);
			}
			else
			{
				// If all selected nodes were excluded, return empty
				$threadFinder->where('node_id', -1);
			}
		}
		else
		{
			// No specific include nodes: Apply Exclude Nodes globally
			if (!empty($excludeNodeIds))
			{
				$threadFinder->where('node_id', '<>', $excludeNodeIds);
			}
		}

		// 2. Resolve Exclude Threads
		if (!empty($excludeThreadIds))
		{
			$threadFinder->where('thread_id', '<>', $excludeThreadIds);
		}

		// 3. Sorting / Ordering
		if ($orderType == 'sticky')
		{
			$threadFinder->where('sticky', 1)->order('last_post_date', 'DESC');
		}
		else if ($orderType == 'view_count')
		{
			$threadFinder->order('view_count', 'DESC');
		}
		else if ($orderType == 'post_date')
		{
			$threadFinder->order('post_date', 'DESC');
		}
		else
		{
			$threadFinder->order('last_post_date', 'DESC');
		}

		$threads = $threadFinder->fetch();

		foreach ($threads as $threadId => $thread)
		{
			if (!$thread->canView()
				|| $thread->isIgnored()
				|| $visitor->isIgnoring($thread->last_post_user_id)
			)
			{
				unset($threads[$threadId]);
			}
		}

		return $threads->slice(0, $limit, true);
	}

	protected function getNewMembers($limit, $visitor)
	{
		/** @var \XF\Finder\UserFinder $userFinder */
		$userFinder = $this->finder(\XF\Finder\UserFinder::class);
		$users = $userFinder
			->where('user_state', 'valid')
			->where('is_banned', 0)
			->order('register_date', 'DESC')
			->limit($limit)
			->fetch();

		foreach ($users as $userId => $user)
		{
			if ($visitor->isIgnoring($userId))
			{
				unset($users[$userId]);
			}
		}

		return $users;
	}

	protected function parseThreadIds($rawInput): array
	{
		if (is_array($rawInput))
		{
			return array_filter(array_map('intval', $rawInput));
		}

		if (!is_string($rawInput) || trim($rawInput) === '')
		{
			return [];
		}

		$parts = preg_split('/[\s,]+/', $rawInput);
		$ids = [];
		foreach ($parts as $part)
		{
			$id = (int)trim($part);
			if ($id > 0)
			{
				$ids[] = $id;
			}
		}

		return array_unique($ids);
	}

	public function verifyOptions(Request $request, array &$options, &$error = null)
	{
		$options = $request->filter([
			'limit' => 'uint',
			'style' => 'str',
			'node_ids' => 'array-uint',
			'exclude_thread_ids' => 'str',
		]);

		if (in_array(0, $options['node_ids']))
		{
			$options['node_ids'] = [0];
		}

		return true;
	}
}
