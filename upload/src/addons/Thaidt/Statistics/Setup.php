<?php

namespace Thaidt\Statistics;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

	public function installStep1()
	{
		$this->createTables();
		$this->insertDefaultTabs();
	}

	public function upgradeStep1000070()
	{
		$this->createTables();
		$this->insertDefaultTabs();
	}

	public function uninstallStep1()
	{
		$this->dropTables();
	}

	protected function createTables()
	{
		$sm = $this->schemaManager();

		$sm->createTable('xf_thaidt_statistics_tab', function (\XF\Db\Schema\Create $table) {
			$table->addColumn('tab_id', 'int')->autoIncrement();
			$table->addColumn('title', 'varchar', 100);
			$table->addColumn('tab_type', 'varchar', 50)->setDefault('recent_replies');
			$table->addColumn('node_ids', 'mediumblob')->nullable();
			$table->addColumn('display_order', 'int')->setDefault(10);
			$table->addColumn('active', 'tinyint')->setDefault(1);
			$table->addPrimaryKey('tab_id');
			$table->addKey('display_order');
		});
	}

	protected function insertDefaultTabs()
	{
		$db = $this->db();
		$count = $db->fetchOne("SELECT COUNT(*) FROM xf_thaidt_statistics_tab");
		if (!$count)
		{
			$db->insert('xf_thaidt_statistics_tab', [
				'title' => 'Vừa trả lời',
				'tab_type' => 'recent_replies',
				'node_ids' => json_encode([]),
				'display_order' => 10,
				'active' => 1
			]);
			$db->insert('xf_thaidt_statistics_tab', [
				'title' => 'Thành viên mới đăng',
				'tab_type' => 'latest_threads',
				'node_ids' => json_encode([]),
				'display_order' => 20,
				'active' => 1
			]);
			$db->insert('xf_thaidt_statistics_tab', [
				'title' => 'Việc làm',
				'tab_type' => 'forum_nodes',
				'node_ids' => json_encode([]),
				'display_order' => 30,
				'active' => 1
			]);
			$db->insert('xf_thaidt_statistics_tab', [
				'title' => 'Vừa gia nhập',
				'tab_type' => 'new_members',
				'node_ids' => json_encode([]),
				'display_order' => 40,
				'active' => 1
			]);
		}
	}

	protected function dropTables()
	{
		$sm = $this->schemaManager();
		$sm->dropTable('xf_thaidt_statistics_tab');
	}
}
