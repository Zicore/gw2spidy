<?php

namespace GW2Spidy\WorkerQueue;

use GW2Spidy\Queue\WorkerQueueManager;
use GW2Spidy\Queue\WorkerQueueItem;

use GW2Spidy\DB\BuyListing;
use GW2Spidy\DB\SellListing;

use GW2Spidy\DB\Item;
use GW2Spidy\DB\ItemQuery;
use GW2Spidy\TradingPostSpider;

use GW2Spidy\DB\ItemType;
use GW2Spidy\DB\ItemSubType;

class ItemListingsDBWorker extends ItemDBWorker implements Worker {
    public function getRetries() {
        return 0;
    }

    public function work(WorkerQueueItem $item) {
        $item = $item->getData();

        $this->buildListingsDB($item);
    }

    public function buildListingsDB($input) {
        // ensure we're always working with an array of items
        $items = ($input instanceof Item) ? array($input->getDataId() => $input) : $input;

        if ($itemsData = TradingPostSpider::getInstance()->getItemsByIds(array_keys($items))) {
            $exceptions = array();

            foreach ($itemsData as $itemData) {
                try {
                    $this->storeItemData($itemData, null, null, $items[$itemData['data_id']]);
                } catch (Exception $e) {
                    if ($e->getCode() == ItemDBWorker::ERROR_CODE_NO_LONGER_EXISTS || strstr("CurlRequest failed [[ 401 ]]", $e->getMessage())) {
                        continue;
                    }

                    $exceptions[] = $e;
                }
            }

            if (count($exceptions) == 1) {
                throw $exceptions[0];
            } else if (count($exceptions) > 1) {
                $s = "";
                foreach ($exceptions as $e) {
                    $s .= $e->getMessage() . " \n ------------ \n";
                }

                throw new Exception("Multiple Exceptions were thrown: \n {$s}");
            }
        }
    }

    public static function enqueueWorker($input) {
        $queueItem = new WorkerQueueItem();
        $queueItem->setWorker("\\GW2Spidy\\WorkerQueue\\ItemListingsDBWorker");
        // $queueItem->setPriority(WorkerQueueItem::PRIORITY_LISTINGSDB);
        $queueItem->setData($input);

        WorkerQueueManager::getInstance()->enqueue($queueItem);

        return $queueItem;
    }
}

?>