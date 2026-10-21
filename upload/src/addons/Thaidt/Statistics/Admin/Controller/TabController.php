<?php

namespace Thaidt\Statistics\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class TabController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('option');
	}

	public function actionIndex()
	{
		$tabRepo = $this->getTabRepo();
		$tabs = $tabRepo->findTabsForList()->fetch();

		$viewParams = [
			'tabs' => $tabs,
		];
		return $this->view('Thaidt\Statistics:Tab\List', 'thaidt_statistics_tab_list', $viewParams);
	}

	public function actionAdd()
	{
		$tab = $this->getTabRepo()->create('Thaidt\Statistics:Tab');
		return $this->tabAddEditResponse($tab);
	}

	public function actionEdit(ParameterBag $params)
	{
		$tab = $this->assertTabExists($params['tab_id']);
		return $this->tabAddEditResponse($tab);
	}

	protected function tabAddEditResponse(\Thaidt\Statistics\Entity\Tab $tab)
	{
		/** @var \XF\Repository\NodeRepository $nodeRepo */
		$nodeRepo = $this->repository(\XF\Repository\NodeRepository::class);
		$nodeTree = $nodeRepo->createNodeTree($nodeRepo->getFullNodeList());

		$viewParams = [
			'tab' => $tab,
			'nodeTree' => $nodeTree,
		];
		return $this->view('Thaidt\Statistics:Tab\Edit', 'thaidt_statistics_tab_edit', $viewParams);
	}

	public function actionSave(ParameterBag $params)
	{
		if ($params['tab_id'])
		{
			$tab = $this->assertTabExists($params['tab_id']);
		}
		else
		{
			$tab = $this->getTabRepo()->create('Thaidt\Statistics:Tab');
		}

		$input = $this->filter([
			'title' => 'str',
			'tab_type' => 'str',
			'node_ids' => 'array-uint',
			'display_order' => 'uint',
			'active' => 'bool',
		]);

		$tab->bulkSet($input);
		$tab->save();

		return $this->redirect($this->buildLink('thaidt-statistics'));
	}

	public function actionDelete(ParameterBag $params)
	{
		$tab = $this->assertTabExists($params['tab_id']);

		if ($this->isPost())
		{
			$tab->delete();
			return $this->redirect($this->buildLink('thaidt-statistics'));
		}

		$viewParams = [
			'tab' => $tab,
		];
		return $this->view('Thaidt\Statistics:Tab\Delete', 'thaidt_statistics_tab_delete', $viewParams);
	}

	public function actionToggle()
	{
		/** @var \XF\ControllerPlugin\TogglePlugin $plugin */
		$plugin = $this->plugin(\XF\ControllerPlugin\TogglePlugin::class);
		return $plugin->actionToggle('Thaidt\Statistics:Tab');
	}

	public function actionSort()
	{
		/** @var \XF\ControllerPlugin\SortPlugin $plugin */
		$plugin = $this->plugin(\XF\ControllerPlugin\SortPlugin::class);
		$sortData = $this->filter('options', 'json-array');
		
		$tabs = $this->getTabRepo()->findTabsForList()->fetch();
		$plugin->sortFlat($sortData, $tabs);

		return $this->redirect($this->buildLink('thaidt-statistics'));
	}

	/**
	 * @param int $id
	 * @return \Thaidt\Statistics\Entity\Tab
	 */
	protected function assertTabExists($id)
	{
		return $this->assertRecordExists('Thaidt\Statistics:Tab', $id);
	}

	/**
	 * @return \Thaidt\Statistics\Repository\TabRepository
	 */
	protected function getTabRepo()
	{
		return $this->repository('Thaidt\Statistics:Tab');
	}
}
