<?php

namespace Thaidt\Statistics\Pub\Controller;

use Thaidt\Statistics\Widget\RecentReplies;
use XF\Pub\Controller\AbstractController;
use XF\Widget\WidgetConfig;

use function class_exists;

class StatisticsController extends AbstractController
{
	public function actionIndex()
	{
		return $this->actionRefresh();
	}

	public function actionRefresh()
	{
		if (class_exists(WidgetConfig::class))
		{
			$widgetConfig = WidgetConfig::create([
				'widget_id' => 0,
				'widget_key' => '',
				'definition_id' => 'thaidt_recent_replies',
				'title' => '',
				'options' => [],
			]);
			$widget = new RecentReplies($this->app(), $widgetConfig);
		}
		else
		{
			$widget = new RecentReplies($this->app(), []);
		}

		$renderedHtml = (string)$widget->render();

		$reply = $this->view('Thaidt\Statistics:Statistics\Refresh');
		$reply->setJsonParams([
			'status' => 'ok',
			'html' => $renderedHtml,
		]);

		return $reply;
	}
}
