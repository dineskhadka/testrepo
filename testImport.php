<?php

if (version_compare(phpversion(), '5.2.0', '<') === true) {
    die('ERROR: Whoops, it looks like you have an invalid PHP version. Magento supports PHP 5.2.0 or newer.');
}

require_once('app/Mage.php');
Mage::app();

class Test_Import extends Mage_Core_Model_Abstract
{

    protected $_file = null;
    protected $_config = array(
        'delimeter' => ',',
        'enclose' => '"',
        'create_options' => false,
        'create_categories' => false,
        'absent_products' => false,
        'update_indexes' => true,
    );
    protected $_attributeCollection = null;
    protected $_categoryCollection = null;
    protected $_attribute_codes = array();
    protected $_static_attributes = array();
    protected $_eav_attributes = array();
    protected $_attribute_options = array();
    protected $_sku2id = array();
    protected $_completesku = array();
    protected $_allsku = array();
    protected $_checksku = array();
    protected $_product_attribute_sets = array();
    protected $_attribute_links = array();
    protected $_result = array('created' => 0, 'updated' => 0, 'skiped' => 0, 'errors' => array(), 'messages' => array());

    public function __construct($params = array())
    {

        if (isset($params['config']) && is_array($params['config'])) {

            $this->_config = array_merge($this->_config, $params['config']);
        }
    }

    public function run()
    {

        //$datafile = $this->_saveFile($requestFileName, 'fast-product-import.csv'); //save file and return path
        $datafile = 'data/Enhanced-GB.txt';

        $this->_file = fopen($datafile, "r");


        $raw_attributes = array("tax_class_id", "gift_message_available", "enable_googlecheckout");

        $itemModel = Mage::getModel('catalog/product');
        $itemStockModel = Mage::getModel('cataloginventory/stock_item');

        $store_id = 0;
        $website_ids = array();

        $default_attribute_set_id = $itemModel->getResource()->getEntityType()->getDefaultAttributeSetId();

        $entity_type_id = $itemModel->getResource()->getTypeId();


        $attributeSetCollection = Mage::getResourceModel('eav/entity_attribute_set_collection')
                ->setEntityTypeFilter($entity_type_id);
        foreach ($attributeSetCollection as $set) {
            $this->_product_attribute_sets[$set->getAttributeSetName()] = $set->getId();
        }

        $default_stock_id = Mage_CatalogInventory_Model_Stock::DEFAULT_STOCK_ID;


        $this->_attributeCollection = Mage::getResourceModel('catalog/product_attribute_collection')
                ->setItemObjectClass('catalog/resource_eav_attribute');

        $this->_categoryCollection = Mage::getResourceModel('catalog/category_collection')->addAttributeToSelect('name');
        //$this->_attribute_codes = $this->_getColumnNames(); //return array
        //$this->_validateRequiredAttributes($this->_attribute_codes);

        $this->_prepareAttributes();

        $core_resource = Mage::getSingleton('core/resource');

        $read_connection = $core_resource->getConnection('read');
        /** @var $write_connection Varien_Db_Adapter_Pdo_Mysql */
        $write_connection = $core_resource->getConnection('write');

        $product_table = $core_resource->getTableName('catalog/product');
        $stock_item_table = $core_resource->getTableName('cataloginventory/stock_item');
        $stock_status_table = $core_resource->getTableName('cataloginventory/stock_status');

        $category_product_table = $core_resource->getTableName('catalog/category_product');
        $product_website_table = $core_resource->getTableName('catalog/product_website');

        $gallery_table = $core_resource->getTableName('catalog/product_attribute_media_gallery');
        $gallery_value_table = $core_resource->getTableName('catalog/product_attribute_media_gallery_value');

//        $all_sku = '"' . implode('","', $this->_allsku) . '"';
//        $check_sku = $this->_checksku;
//
//        $q = "SELECT `sku`, `entity_id` FROM {$product_table} WHERE `sku` IN ($all_sku)";
//
//        $this->_sku2id = $read_connection->fetchPairs($q);

        rewind($this->_file);

        $row_num = 1;

        $write_connection->beginTransaction();

        $inserts = array();

        while ($line = fgets($this->_file, 100000)):
            try {
                if ($row_num) {
                    $_productArray = explode("\t", $line);
//                    echo '<pre>';
//                    print_r($_productArray);
//                    die;
                    $values = array();
                    $sku = $_productArray[0];
                    if (empty($sku)) {

                        throw new Mage_Core_Exception('SKU is empty');
                    }

                    $product_id = false;
                    $website_ids = array();

//                    $test = $itemModel->loadByAttribute('sku', $_productArray[0]);
//
//                    if (!empty($test)) {
//                        echo __LINE__ . ' ';
//                        continue;
//                    }
                    /*
                      if ($websiteCodes = $this->_getColumnValue('websites', $row)) {

                      $websiteCodes = explode(',', $websiteCodes);

                      foreach ($websiteCodes as $websiteCode) {
                      try {
                      $website = Mage::app()->getWebsite(trim($websiteCode));
                      if (!in_array($website->getId(), $website_ids)) {
                      $website_ids[] = $website->getId();
                      }
                      } catch (Exception $e) {

                      }
                      }
                      } else {

                      $default_website = $websiteCollection = Mage::getResourceModel('core/website_collection')
                      ->addFieldToFilter('is_default', true)
                      ->getFirstItem();

                      $website_ids = array($default_website->getId());
                      }
                     */
                    $default_website = $websiteCollection = Mage::getResourceModel('core/website_collection')
                            ->addFieldToFilter('is_default', true)
                            ->getFirstItem();

                    $website_ids = array($default_website->getId());

                    if (isset($this->_sku2id[$sku])) {

                        $product_id = $this->_sku2id[$sku];
                    }

                    foreach ($this->_static_attributes as $attribute) {

                        $attribute_code = $attribute->getAttributeCode();
                        //echo $attribute_code . ' : ';
                        /* category_ids : created_at : has_options : is_imported : required_options : sku : updated_at */
                        if ($attribute_code == 'category_ids') {
                            //$values[$attribute_code] = array(2);
                        } else if ($attribute_code == 'created_at') {
                            $values[$attribute_code] = strtotime('now');
                        } else if ($attribute_code == 'has_options') {
                            
                        } else if ($attribute_code == 'is_imported') {
                            
                        } else if ($attribute_code == 'required_options') {
                            
                        } else if ($attribute_code == 'sku') {
                            $values[$attribute_code] = $_productArray[0];
                        } else if ($attribute_code == 'updated_at') {
                            $values[$attribute_code] = strtotime('now');
                        }

//                        $value = $this->_getColumnValue($attribute_code, $row);
//
//                        if ($value !== false) {
//
//                            $values[$attribute_code] = $this->_getColumnValue($attribute_code, $row);
//                        }
                    }

                    if (!empty($values) || !$product_id) {

                        if (!$product_id) {

                            $values['entity_type_id'] = $entity_type_id;
                            /*
                              if (!isset($values['type_id']) || empty($values['type_id'])) {

                              if ($type = $this->_getColumnValue('type', $row)) {

                              $values['type_id'] = $type;
                              } else {

                              $values['type_id'] = 'simple';
                              }
                              } */
                            $values['type_id'] = 'simple';

                            if (!isset($values['attribute_set_id']) || empty($values['attribute_set_id'])) {

                                $values['attribute_set_id'] = $default_attribute_set_id;

                                /*
                                  if ($attribute_set_name = $this->_getColumnValue('attribute_set', $row)) {

                                  if (isset($this->_product_attribute_sets[$attribute_set_name])) {
                                  $values['attribute_set_id'] = $this->_product_attribute_sets[$attribute_set_name];
                                  }
                                  } */
                            }
                        }
                        try {
                            /*
                              if ($attribute_set_name = $this->_getColumnValue('attribute_set', $row)) {

                              if (isset($this->_product_attribute_sets[$attribute_set_name])) {
                              $values['attribute_set_id'] = $this->_product_attribute_sets[$attribute_set_name];
                              }
                              }
                             */
                            if ($product_id) {

                                $write_connection->update($product_table, $values, "entity_id = {$product_id}");

                                $this->_result['updated']++;
                            } else {
                                $write_connection->insert($product_table, $values);

                                $product_id = $write_connection->lastInsertId();

                                foreach ($website_ids as $website_id) {
                                    $write_connection->insertOnDuplicate($product_website_table, array('product_id' => $product_id, 'website_id' => $website_id));
                                }
                                $this->_sku2id[$sku] = $product_id;

                                $this->_result['created']++;
                            }
                        } catch (Exception $e) {
                            echo $e->getMessage();
                            echo '<pre>';
                            print_r($e);
                        }
                    }

                    if ($product_id) {
                        $qty = isset($_productArray[8]) ? $_productArray[8] : '';

                        $is_in_stock = Mage_CatalogInventory_Model_Stock::STOCK_IN_STOCK;

                        $values = array(
                            'product_id' => $product_id,
                            'stock_id' => $default_stock_id,
                        );

                        // if ($this->_getColumnValue('qty', $row) !== false) {
                        $values['qty'] = $qty;
                        //}
                        //if ($this->_getColumnValue('is_in_stock', $row) !== false) {
                        $values['is_in_stock'] = $is_in_stock;
                        //}
                        /*
                          if ($this->_getColumnValue('use_config_min_qty', $row) !== false) {
                          $values['use_config_min_qty'] = $this->_getColumnValue('use_config_min_qty', $row);
                          }

                          if ($this->_getColumnValue('use_config_backorders', $row) !== false) {
                          $values['use_config_backorders'] = $this->_getColumnValue('use_config_backorders', $row);
                          }

                          if ($this->_getColumnValue('backorders', $row) !== false) {
                          $values['backorders'] = $this->_getColumnValue('backorders', $row);
                          }

                          if ($this->_getColumnValue('manage_stock', $row) !== false) {
                          $values['manage_stock'] = $this->_getColumnValue('manage_stock', $row);
                          } */

                        $values['manage_stock'] = 1;
                        /*
                          if ($this->_getColumnValue('use_config_manage_stock', $row) !== false) {
                          $values['use_config_manage_stock'] = $this->_getColumnValue('use_config_manage_stock', $row);
                          }
                         */
                        $values['use_config_manage_stock'] = 1;


                        if (isset($values['qty']) && isset($values['is_in_stock'])) {
                            $write_connection->query(sprintf('DELETE FROM %s WHERE `product_id` = %d', $stock_item_table, $product_id));
                            $write_connection->insert($stock_item_table, $values);
                        }

                        if (isset($values['qty']) && isset($values['is_in_stock'])) {
                            $write_connection->query(sprintf('DELETE FROM %s WHERE `product_id` = %d', $stock_status_table, $product_id));
                            foreach ($website_ids as $website_id) {

                                $values = array(
                                    'product_id' => $product_id,
                                    'stock_id' => $default_stock_id,
                                    'website_id' => $website_id,
                                );

//                                if ($this->_getColumnValue('qty', $row) !== false) {
//                                    $values['qty'] = $qty;
//                                }
                                $values['qty'] = $qty;
//                                if ($this->_getColumnValue('is_in_stock', $row) !== false) {
//                                    $values['stock_status'] = $is_in_stock;
//                                }
                                $values['stock_status'] = $is_in_stock;
                                $write_connection->insert($stock_status_table, $values);
                            }
                        }

                        foreach ($this->_eav_attributes as $attribute_code => $attribute) {
                            $values = array(
                                'entity_id' => $write_connection->quote($product_id),
                                'attribute_id' => $write_connection->quote($attribute->getId()),
                                'entity_type_id' => $write_connection->quote($entity_type_id),
                                'store_id' => $write_connection->quote($store_id),
                                'value' => null,
                            );

                            $table = $attribute->getBackendTable();
                            /*
                              if ($attribute->getFrontendInput() == 'select' || $attribute->getFrontendInput() == 'multiselect') {


                              $value = $this->_getColumnValue($attribute_code, $row);

                              if ($value !== false) {

                              $write_connection->query(sprintf('DELETE FROM %s WHERE `attribute_id` = %d AND `entity_id` = %d', $table, $attribute->getId(), $product_id));

                              if ($attribute->getFrontendInput() == 'multiselect') {

                              $base_value = explode(',', $value);

                              $multi_value = array();

                              foreach ($base_value as $value) {
                              if (isset($this->_attribute_options[$attribute_code]['label2value'][$value])) {

                              $multi_value[] = $this->_attribute_options[$attribute_code]['label2value'][$value];
                              }
                              }



                              if (!empty($multi_value)) {
                              $values['value'] = sprintf('"%s"', mysql_escape_string(implode(',', $multi_value)));
                              }
                              } else {

                              if (isset($this->_attribute_options[$attribute_code]['label2value'][$value])) {
                              $values['value'] = sprintf('"%s"', mysql_escape_string($this->_attribute_options[$attribute_code]['label2value'][$value]));
                              }

                              if (in_array($attribute_code, $raw_attributes)) {
                              $values['value'] = $value;
                              }
                              }


                              if ($values['value'] !== null) {



                              if (!isset($inserts[$attribute_code])) {

                              $inserts[$attribute_code] = array(
                              'q' => sprintf('INSERT INTO `%s` (`%s`) VALUES ', $table, implode('`,`', array_keys($values))),
                              'v' => array(sprintf('(%s)', implode(',', $values))),
                              );
                              } else {

                              $inserts[$attribute_code]['v'][] = sprintf('(%s)', implode(',', $values));
                              }
                              }
                              }
                              } else {
                             */

                            if ($attribute_code == 'name') {
                                $value = $_productArray[2];
                            } elseif ($attribute_code == 'cost') {
                                $value = $_productArray[4];
                            } elseif ($attribute_code == 'description') {
                                $value = $_productArray[3];
                            } elseif ($attribute_code == 'meta_description') {
                                $value = $_productArray[3];
                            } elseif ($attribute_code == 'meta_keyword') {
                                $value = $_productArray[5];
                            } elseif ($attribute_code == 'meta_title') {
                                $value = $_productArray[2];
                            } elseif ($attribute_code == 'price') {
                                $value = $_productArray[5];
                            } elseif ($attribute_code == 'short_description') {
                                $value = $_productArray[3];
                            } elseif ($attribute_code == 'weight') {
                                $value = $_productArray[20];
                            } else {
                                continue;
                            }

                            //$value = $this->_getColumnValue($attribute_code, $row);

                            if ($value) {

                                $write_connection->query(sprintf('DELETE FROM %s WHERE `attribute_id` = %d AND `entity_id` = %d', $table, $attribute->getId(), $product_id));

                                $values['value'] = sprintf('"%s"', mysql_escape_string($value));

                                if (!isset($inserts[$attribute_code])) {

                                    $inserts[$attribute_code] = array(
                                        'q' => sprintf('INSERT INTO `%s` (`%s`) VALUES ', $table, implode('`,`', array_keys($values))),
                                        'v' => array(sprintf('(%s)', implode(',', $values))),
                                    );
                                } else {

                                    $inserts[$attribute_code]['v'][] = sprintf('(%s)', implode(',', $values));
                                }
                            }
                            /* } */
                        }
                        //$att_array = array("visibility", "tax_class_id", "weight", "price", "status", "authorized_dealer");
                        $att_array = array("visibility", "tax_class_id", "status");
                        foreach ($att_array as $att) {
                            $attributeInfo = Mage::getResourceModel('eav/entity_attribute_collection')
                                    ->setCodeFilter($att)
                                    ->getFirstItem();
                            $attributetabel = $attributeInfo->getBackendTable();
                            $attributeId = $attributeInfo->getAttributeId();
                            $entityTypeId = $attributeInfo->getEntityTypeId();

                            $frontendInput = $attributeInfo->getFrontendInput();
                            /*
                              if ($frontendInput == 'select') {
                              $attributeOptions = $attributeInfo->getSource()->getAllOptions(false);
                              foreach ($attributeOptions as $option) {
                              if ($option['label'] == $this->_getColumnValue($att, $row)) {
                              $option_value = $option['value'];
                              }
                              }
                              } else {
                              $option_value = $this->_getColumnValue($att, $row);
                              } */
                            if ($att == 'visibility') {
                                $option_value = 4;
                            } elseif ($att == 'tax_class_id') {
                                $option_value = 0;
                            } elseif ($att == 'status') {
                                $option_value = 1;
                            }
                            $record = $read_connection->fetchAll("SELECT * FROM `" . $attributetabel . "` WHERE `attribute_id`='$attributeId' AND `entity_id`='$product_id'");
                            if (count($record) != 0) {
                                
                            } else {

                                $write_connection->query("INSERT INTO `" . $attributetabel . "` (`entity_type_id`,`attribute_id`,`store_id`,`entity_id`,`value`) VALUES ('$entityTypeId','$attributeId','$store_id','$product_id','$option_value')");
                            }
                        }

                        //save categories
                        /*
                          $category_ids = array();

                          if ($_category_ids = $this->_getColumnValue('category_ids', $row)) {

                          $_category_ids = explode(',', $_category_ids);

                          foreach ($_category_ids as $id) {

                          if ($category = $this->_categoryCollection->getItemById($id)) {

                          $category_ids[] = $id;
                          }
                          }
                          }
                         */
                        //if ($categories = $this->_getColumnValue('categories', $row)) {
                        //$categories = $_productArray[17] . "," . $_productArray[18];
                        //$categories = explode(',', $categories);
                        $categories = array($_productArray[17], $_productArray[18]);
                        $category_ids = array();
                        foreach ($website_ids as $website_id) {

                            $root_category_id = Mage::app()->getWebsite($website_id)->getDefaultGroup()->getRootCategoryId();
                            $this->_categoryCollection->getItemById($root_category_id);

                            $website_category_ids = $this->_getCategoryIds($categories, $this->_categoryCollection->getItemById($root_category_id));
                            $category_ids = array_merge($category_ids, $website_category_ids);
                            /*
                              foreach ($categories as $category_path) {

                              $category_path = explode('/', $category_path);
                              $website_category_ids = $this->_getCategoryIds($category_path, $this->_categoryCollection->getItemById($root_category_id));
                              $category_ids = array_merge($category_ids, $website_category_ids);
                              } */
                        }

                        //}

                        if (!empty($category_ids)) {

                            //$write_connection->query(sprintf('DELETE FROM %s WHERE `product_id` = %d', $category_product_table, $product_id));

                            $category_ids = array_unique($category_ids);
                            foreach ($category_ids as $id) {
                                $write_connection->insertOnDuplicate($category_product_table, array('category_id' => $id, 'product_id' => $product_id), array('category_id', 'product_id'));
                            }
                        }
                        /*
                          //import images
                          $images = $this->_getColumnValue('images', $row);
                          $images = explode(',', $images);
                          if ($base_image = $this->_getColumnValue('image', $row)) {
                          $images[] = $base_image;
                          }
                          if ($small_image = $this->_getColumnValue('small_image', $row)) {
                          $images[] = $small_image;
                          }
                          if ($thumbnail = $this->_getColumnValue('thumbnail', $row)) {
                          $images[] = $thumbnail;
                          }

                          $images = array_unique($images);

                          if (!empty($images)) {

                          $exclude_images = (string) $this->_getColumnValue('exclude_images', $row);
                          $exclude_images = explode(',', $exclude_images);

                          foreach ($images as $image_filename) {

                          $_image_filename = trim(trim($image_filename), DS);

                          $filename = Mage::getBaseDir('media') . DS . 'import' . DS . $_image_filename;

                          if ($_image_filename && file_exists($filename)) {



                          $filedata = $this->addImage($filename, null, false);

                          $values = array(
                          'attribute_id' => $this->_attributeCollection->getItemByColumnValue('attribute_code', 'media_gallery')->getAttributeId(),
                          'entity_id' => $product_id,
                          'value' => $filedata['file'],
                          );

                          $write_connection->insert($gallery_table, $values);

                          $write_connection->insert($gallery_value_table, array(
                          'value_id' => $write_connection->lastInsertId(),
                          'store_id' => $store_id,
                          'label' => '',
                          'position' => null,
                          'disabled' => (int) in_array($image_filename, $exclude_images),
                          ));



                          $image_attribute = array();

                          if ($image_filename == $base_image) {
                          $image_attribute[] = $this->_attributeCollection->getItemByColumnValue('attribute_code', 'image');
                          }
                          if ($image_filename == $small_image) {
                          $image_attribute[] = $this->_attributeCollection->getItemByColumnValue('attribute_code', 'small_image');
                          }
                          if ($image_filename == $thumbnail) {
                          $image_attribute[] = $this->_attributeCollection->getItemByColumnValue('attribute_code', 'thumbnail');
                          }




                          if (!empty($image_attribute)) {

                          foreach ($image_attribute as $attribute) {

                          $table = $attribute->getBackendTable();

                          $values = array(
                          'entity_id' => $product_id,
                          'attribute_id' => $attribute->getId(),
                          'entity_type_id' => $entity_type_id,
                          'store_id' => $store_id,
                          'value' => $filedata['file'],
                          );

                          $write_connection->query(sprintf('DELETE FROM %s WHERE `attribute_id` = %d AND `entity_id` = %d', $table, $attribute->getId(), $product_id));
                          $write_connection->insert($table, $values);
                          }
                          }
                          }
                          }
                          }
                         */
                    }
                    /*
                      if ($links = $this->_getColumnValue('related', $row)) {
                      $this->_attribute_links[$product_id]['related'] = explode(',', $links);
                      }

                      if ($links = $this->_getColumnValue('up_sell', $row)) {
                      $this->_attribute_links[$product_id]['up_sell'] = explode(',', $links);
                      }

                      if ($links = $this->_getColumnValue('cross_sell', $row)) {
                      $this->_attribute_links[$product_id]['cross_sell'] = explode(',', $links);
                      }

                      $this->_completesku[] = $sku;
                     */
                    if ($row_num % 200 == 0) {

                        $eav_inserts = array();

                        foreach ($inserts as $_insert) {

                            $_insert['v'] = implode(',', $_insert['v']);

                            $eav_inserts[] = implode(' ', $_insert);
                        }

                        if (!empty($eav_inserts)) {

                            $write_connection->query(implode(";\n", $eav_inserts));

                            unset($inserts, $eav_inserts);

                            $inserts = array();
                        }



                        $write_connection->commit();
                        $write_connection->beginTransaction();
                    }
                }
            } catch (Mage_Core_Exception $e) {

                $this->_result['skiped']++;
                $this->_result['errors'][] = sprintf("<strong>Error (line %d): </strong> %s", $row_num, $e->getMessage());
            } catch (Exception $e) {

                $this->_result['skiped']++;
                $this->_result['errors'][] = sprintf("<strong>Error (line %d): </strong> %s", $row_num, $e->getMessage());
            }

            $row_num++;

        endwhile; //while (($row = fgetcsv($this->_file, null, $this->_config['delimeter'], $this->_config['enclose'])) !== false):

        $eav_inserts = array();

        foreach ($inserts as $_insert) {

            $_insert['v'] = implode(',', $_insert['v']);

            $eav_inserts[] = implode(' ', $_insert);
        }

        if (!empty($eav_inserts)) {
            $write_connection->query(implode(";\n", $eav_inserts));
        }

        $write_connection->commit();

        if (is_array($this->_attribute_links) && count($this->_attribute_links)) {

            $write_connection->beginTransaction();

            $link_types = array("related" => Mage_Catalog_Model_Product_Link::LINK_TYPE_RELATED, "up_sell" => Mage_Catalog_Model_Product_Link::LINK_TYPE_UPSELL, "cross_sell" => Mage_Catalog_Model_Product_Link::LINK_TYPE_CROSSSELL);

            $table = $core_resource->getTableName('catalog/product_link');

            foreach ($this->_attribute_links as $product_id => $links) {

                foreach ($link_types as $link_type => $link_type_id) {

                    if (isset($links[$link_type]) && count($links[$link_type])) {

                        $deleteCondition = $write_connection->quoteInto('product_id=?', $product_id)
                                . $write_connection->quoteInto(' AND link_type_id=?', $link_type_id);

                        $write_connection->delete($table, $deleteCondition);

                        $linked_products = array();
                        foreach ($links[$link_type] as $link) {

                            $linkedProductId = Mage::getModel('catalog/product')->getIdBySku($link);
                            if ($linkedProductId) {
                                if (!in_array($linkedProductId, $linked_products)) {
                                    $linked_products[] = $linkedProductId;

                                    $write_connection->insert($table, array(
                                        'product_id' => $product_id,
                                        'linked_product_id' => $linkedProductId,
                                        'link_type_id' => $link_type_id
                                    ));
                                } else {
                                    
                                }
                            }
                        }
                    }
                }
            }

            $write_connection->commit();
        }

        if ($this->_config['update_indexes']) {

            $entity = new Varien_Object();
            Mage::getSingleton('index/indexer')->processEntityAction(
                    $entity, Mage_Catalog_Model_Convert_Adapter_Product::ENTITY, Mage_Index_Model_Event::TYPE_SAVE
            );
        }
    }

    protected function _getCategoryIds($path, $parent_category, $result = array())
    {
        if ($parent_category) {
            $category_name = array_shift($path);
            $category_id = null;

            $child_category_ids = $parent_category->getResource()->getChildren($parent_category, false);
            foreach ($child_category_ids as $id) {
                $category = $this->_categoryCollection->getItemById($id);

                if ($category && $category_name == $this->_categoryCollection->getItemById($id)->getName()) {
                    $category_id = $id;
                    break;
                }
            }
            if (!$category_id) {// && $this->_config['create_categories']) {
                $category = Mage::getModel('catalog/category');
                $category->setPath($parent_category->getPath());
                $category->setName($category_name);
                $category->setIsActive(true);
                $category->save();
                $category_id = $category->getEntityId();

                $this->_categoryCollection->addItem($category);
            }

            if ($category_id) {

                $result[] = $category_id;
            }

            if (!empty($path)) {

                $next_level_result = $this->_getCategoryIds($path, $category);

                $result = array_merge($result, $next_level_result);
            }
        }

        return $result;
    }

    protected function _getColumnValue($attribute_code, $row)
    {
        static $columns;

        if (is_null($columns)) {
            $columns = array();
        }

        if (isset($columns[$attribute_code])) {

            $columnId = $columns[$attribute_code];
        } else {

            $columnId = array_search($attribute_code, $this->_attribute_codes);

            if ($columnId !== false) {
                $columns[$attribute_code] = $columnId;
            } else {
                return false;
            }
        }
        if (isset($row[$columnId])) {
            return trim($row[$columnId]);
        }

        return false;
    }

    protected function _prepareAttributes()
    {

        $attributes = array();

        $_attribute_codes = $this->_attribute_codes;

        if (($key = array_search('images', $_attribute_codes)) !== false)
            unset($_attribute_codes[$key]);
        if (($key = array_search('exclude_images', $_attribute_codes)) !== false)
            unset($_attribute_codes[$key]);
        if (($key = array_search('image', $_attribute_codes)) !== false)
            unset($_attribute_codes[$key]);
        if (($key = array_search('small_image', $_attribute_codes)) !== false)
            unset($_attribute_codes[$key]);
        if (($key = array_search('thumbnail', $_attribute_codes)) !== false)
            unset($_attribute_codes[$key]);
        if (($key = array_search('category_ids', $_attribute_codes)) !== false)
            unset($_attribute_codes[$key]);


        foreach ($this->_attributeCollection as $attribute) {
            $attribute_code = $attribute->getAttributeCode();

//            if (!in_array($attribute_code, $_attribute_codes)) {
//
//                continue;
//            }

            if ($attribute->getBackendType() == 'static') {

                $this->_static_attributes[$attribute_code] = $attribute;
            } else {

                $this->_eav_attributes[$attribute_code] = $attribute;

                if ($attribute->getFrontendInput() == 'select' || $attribute->getFrontendInput() == 'multiselect') {

                    if (false !== ($column = array_search($attribute_code, $_attribute_codes))) {

                        $this->_attribute_options[$attribute_code] = array('column' => $column, 'options' => array(), 'type' => $attribute->getFrontendInput());
                    }
                }
            }
        }

        $all_sku = array();
        /*
          while (($row = fgetcsv($this->_file, null, $this->_config['delimeter'], $this->_config['enclose'])) !== false) {

          foreach ($this->_attribute_options as $attribute_code => $data) {

          if (isset($row[$data['column']])) {

          if ($option_label = trim($row[$data['column']])) {

          if ($this->_attribute_options[$attribute_code]['type'] == 'multiselect') {

          $base_option_label = explode(',', $option_label);
          } else {

          $base_option_label = array($option_label);
          }
          foreach ($base_option_label as $option_label) {

          if (false === array_search($option_label, $this->_attribute_options[$attribute_code]['options'])) {

          $this->_attribute_options[$attribute_code]['options'][] = $option_label;
          }
          }
          }
          }
          }

          $check_sku = array();

          $this->_allsku[] = $this->_getColumnValue('sku', $row);
          $this->_checksku[] = array($this->_getColumnValue('sku', $row), $this->_getColumnValue('category', $row));
          }
         */
        foreach ($this->_attribute_options as $attribute_code => $data) {

            $this->_attribute_options[$attribute_code]['label2value'] = $this->_getAttributeOptions($attribute_code, $data['options']);
        }



        return $this;
    }

    public function getResult()
    {
        return $this->_result;
    }

    protected function _getAttributeOptions($attribute_code, $new_options)
    {

        $attribute = $this->_attributeCollection->getItemByColumnValue('attribute_code', $attribute_code);

        $result = array();

        if ($attribute->getSource() instanceof Mage_Eav_Model_Entity_Attribute_Source_Table) {



            $collection = Mage::getResourceModel('eav/entity_attribute_option_collection')
                    ->setAttributeFilter($attribute->getId())
                    ->setStoreFilter($attribute->getStoreId())
                    ->load();

            $options = $collection->toOptionArray();

            foreach ($new_options as $key => $value) {
                foreach ($options as $item) {
                    if ($item['label'] == $value) {
                        unset($new_options[$key]);
                    }
                }
            }

            if (!empty($new_options) && $this->_config['create_options']) {

                $_option = array();

                foreach ($options as $option) {

                    $_option['value'][$option['value']][0] = $option['label'];
                }

                $i = 0;

                foreach ($new_options as $value) {

                    $_option['value']['options_' . $i][0] = $value;
                    $_option['order']['options_' . $i] = 0;

                    $i++;
                }

                $attribute->setData('option', $_option)->save();

                $collection->clear()->load();
                $options = $collection->toOptionArray();
            }

            foreach ($options as $item) {

                $result[$item['label']] = $item['value'];
            }
        } else {

            foreach ($attribute->getSource()->getAllOptions() as $option) {

                if ($option['value']) {
                    $result[$option['label']] = $option['value'];
                }
            }
        }

        return $result;
    }

    protected function _validateRequiredAttributes($attribute_codes)
    {

        if (empty($attribute_codes)) {
            throw new Mage_Core_Exception('Incorect file format');
        }

        if (!in_array('sku', $attribute_codes)) {
            throw new Mage_Core_Exception('Required attribute "sku" not found.');
        }

        /*
          if(!in_array('websites', $attribute_codes)){
          throw new Mage_Core_Exception('Required attribute "websites" not found.');
          }
         */
    }

    protected function _getColumnNames()
    {

        $column_names = array();

        if ($columns = fgetcsv($this->_file, null, $this->_config['delimeter'], $this->_config['enclose'])) {

            foreach ($columns as $column_name) {

                $column_names[] = trim($column_name);
            }
        }

        return $column_names;
    }

    protected function _saveFile($name, $newname)
    {

        if (isset($_FILES[$name]['name']) && $_FILES[$name]['name'] != '') {

            $uploader = new Varien_File_Uploader($name);

            $uploader->setAllowedExtensions(array('csv'));
            $uploader->setAllowRenameFiles(false);
            $uploader->setFilesDispersion(false);

            $path = Mage::getBaseDir('var') . DS . 'import' . DS;

            Mage::getSingleton('core/config')->createDirIfNotExists($path);

            $uploader->save($path, $newname);

            return $path . $newname;
        }
    }

    public function addImage($file, $mediaAttribute = null, $move = false, $exclude = false)
    {

        $config = Mage::getSingleton('catalog/product_media_config');

        $file = realpath($file);

        if (!$file || !file_exists($file)) {
            Mage::throwException(Mage::helper('catalog')->__('Image does not exist.'));
        }
        $pathinfo = pathinfo($file);



        if (!isset($pathinfo['extension']) || !in_array(strtolower($pathinfo['extension']), array('jpg', 'jpeg', 'gif', 'png'))) {

            Mage::throwException(Mage::helper('catalog')->__('Invalid image file type.'));
        }

        $fileName = Varien_File_Uploader::getCorrectFileName($pathinfo['basename']);
        $dispretionPath = Varien_File_Uploader::getDispretionPath($fileName);
        $fileName = $dispretionPath . DS . $fileName;

        $fileName = $dispretionPath . DS
                . Varien_File_Uploader::getNewFileName($config->getMediaPath($fileName));

        $ioAdapter = new Varien_Io_File();
        $ioAdapter->setAllowCreateFolders(true);
        $distanationDirectory = dirname($config->getMediaPath($fileName));

        try {
            $ioAdapter->open(array(
                'path' => $distanationDirectory
            ));

            if ($move) {
                $ioAdapter->mv($file, $config->getMediaPath($fileName));
            } else {
                $ioAdapter->cp($file, $config->getMediaPath($fileName));
                $ioAdapter->chmod($config->getMediaPath($fileName), 0777);
            }
        } catch (Exception $e) {
            Mage::throwException(Mage::helper('catalog')->__('Failed to move file: %s', $e->getMessage()));
        }

        $fileName = str_replace(DS, '/', $fileName);

        $mediaGalleryData = null;
        $position = 0;
        if (!is_array($mediaGalleryData)) {
            $mediaGalleryData = array(
                'images' => array()
            );
        }

        $position++;
        $mediaGalleryData['images'][] = array(
            'file' => $fileName,
            'position' => $position,
            'label' => '',
            'disabled' => (int) $exclude
        );

        return array_shift($mediaGalleryData['images']);
    }

}

$testImport = new Test_Import();
$testImport->run();