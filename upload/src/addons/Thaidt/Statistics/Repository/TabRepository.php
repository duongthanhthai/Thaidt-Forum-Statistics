<?php

namespace Thaidt\Statistics\Repository;

use XF\Mvc\Entity\Repository;

class TabRepository extends Repository
{
	public function findTabsForList()
	{
		return $this->finder('Thaidt\Statistics:Tab')
			->order('display_order', 'ASC');
	}

	public function findActiveTabs()
	{
		return $this->finder('Thaidt\Statistics:Tab')
			->where('active', 1)
			->order('display_order', 'ASC');
	}
}
