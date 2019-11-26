<?php
/**
 * @package    Com_Api
 * @copyright  Copyright (C) 2009-2014 Techjoomla, Tekdi Technologies Pvt. Ltd. All rights reserved.
 * @license    GNU GPLv2 <http://www.gnu.org/licenses/old-licenses/gpl-2.0.html>
 * @link       http://www.techjoomla.com
 */

use Joomla\CMS\Helper\MediaHelper;
use Joomla\Registry\Registry;

defined('_JEXEC') or die( 'Restricted access' );
require_once JPATH_SITE . '/components/com_content/models/articles.php';
require_once JPATH_SITE . '/components/com_content/models/article.php';

/**
 * Articles Resource
 *
 * @since  3.5
 */
class ArticlesApiResourceArticle extends ApiResource
{
	const POST_TOO_BIG = 2001;
	const IMAGE_TOO_BIG = 2002;
	const IMAGE_EXISTS = 2003;
	const IMAGE_UPLOAD_FAILED = 2004;
	const ARTICLE_DOES_NOT_EXIST = 2008;
	const ARTICLE_ID_MISSING = 2009;

	/**
	 * get Method to get all artcle data
	 *
	 * @return  json
	 *
	 * @since  3.5
	 */
	public function get()
	{
		$this->plugin->setResponse($this->getArticles());
	}

	/**
	 * delete Method to delete article
	 *
	 * @return  json
	 *
	 * @since  3.5
	 */
	public function delete()
	{
		$this->plugin->setResponse('in delete');
	}

	/**
	 * getArticles Method to getArticles data
	 *
	 * @return  array
	 *
	 * @since  3.5
	 */
	public function getArticles()
	{
		$app = JFactory::getApplication();
//		JPluginHelper::importPlugin('content');
//		$dispatcher = JEventDispatcher::getInstance();

		$items = array();
		$article_id = $app->input->get('id', 0, 'INT');
		$catid = $app->input->get('category_id', 0, 'INT');

		// Featured - hide,only,show
		$featured	= $app->input->get('featured', 0, 'INT');
		$created_by	= $app->input->get('created_by', 0, 'INT');
		$search = $app->input->get('search', '', 'STRING');
		$limitstart	= $app->input->get('limitstart', 0, 'INT');
		$limit	= $app->input->get('limit', 0, 'INT');

		$date_filtering	= $app->input->get('date_filtering', '', 'STRING');
		$start_date = $app->input->get('start_date_range', '', 'STRING');
		$end_date = $app->input->get('end_date_range', '', 'STRING');
		$realtive_date = $app->input->get('relative_date', '', 'STRING');

		$listOrdering = $app->input->get('listOrdering', 'a.ordering', 'STRING');
		$listDirection = $app->input->get('listDirection', 'ASC', 'STRING');

		$art_obj = new ContentModelArticles;

		$art_obj->setState('list.ordering', $listOrdering);
		$art_obj->setState('list.direction', $listDirection);

		if ($limit)
		{
			$art_obj->setState('list.start', $limitstart);
			$art_obj->setState('list.limit', $limit);
		}

		// Filter by category
		if ($catid)
		{
			$art_obj->setState('filter.category_id', $catid);
		}

		if ($search)
		{
			$art_obj->setState('list.filter', $search);
		}

		// Filter by auther
		if ($created_by)
		{
			$art_obj->setState('filter.created_by', $created_by);
		}

		// Filter by featured
		if ($featured)
		{
			$art_obj->setState('filter.featured', $featured);
		}

		// Filter by article
		if ($article_id)
		{
			$art_obj->setState('filter.article_id', $article_id);
		}

		// Filtering
		if ($date_filtering)
		{
			$art_obj->setState('filter.date_filtering', $date_filtering);

			if ($date_filtering == 'range')
			{
				$art_obj->setState('filter.start_date_range', $start_date);
				$art_obj->setState('filter.end_date_range', $end_date);
			}
		}

		$rows = $art_obj->getItems();

		$num_articles = $art_obj->getTotal();
		$data[] = new stdClass;

		foreach ($rows as $subKey => $subArray)
		{
//			$dispatcher->trigger('onContentPrepare', array ('com_content.article', &$subArray, &$subArray->params, $limitstart));

			$data[$subKey] = new \stdClass();
			$data[$subKey]->id = $subArray->id;
			$data[$subKey]->title = $subArray->title;
			$data[$subKey]->alias = $subArray->alias;
			$data[$subKey]->teaser = $this->teaser($subArray);
			$data[$subKey]->introtext = $subArray->introtext;
			$data[$subKey]->fulltext = $subArray->fulltext;
			$data[$subKey]->catid = array('catid' => $subArray->catid, 'title' => $subArray->category_title);
			$data[$subKey]->state = $subArray->state;
			$data[$subKey]->created = $subArray->created;
			$data[$subKey]->modified = $subArray->modified;
			$data[$subKey]->publish_up = $subArray->publish_up;
			$data[$subKey]->publish_down = $subArray->publish_down;

			if ($subArray->images)
			{
				$images = json_decode($subArray->images);

				foreach ($images as $key => $value)
				{
					if ($value)
					{
						$images->$key = JURI::base() . $value;
					}
				}

				$data[$subKey]->images = $images;
			}

			$data[$subKey]->access = $subArray->access;
			$data[$subKey]->featured = $subArray->featured;
			$data[$subKey]->language = $subArray->language;
			$data[$subKey]->hits = $subArray->hits;

			if ($subArray->created_by)
			{
				$data[$subKey]->created_by = array('id' => $subArray->created_by, 'name' => $subArray->author);
			}

			$data[$subKey]->tags = $subArray->tags;
		}

		$obj = new stdclass;
		$result = new stdClass;

		if (count($data) > 0)
		{
			$result->results = $data;
			$result->total = $num_articles;
			$obj->success = true;
			$obj->data = $result;

			return $obj;
		}
		else
		{
			$obj->success = false;
			$obj->message = 'System does not have articles';
		}

		return $obj;
	}

	/**
	 * Post is to create / update article
	 *
	 * @return  Boolean
	 *
	 * @since  3.5
	 */
	public function post()
	{
		$this->plugin->setResponse($this->CreateUpdateArticle());
	}

	/**
	 * CreateUpdateArticle is to create / upadte article
	 *
	 * @return  Bolean
	 *
	 * @since  3.5
	 */
	public function CreateUpdateArticle()
	{
		if (version_compare(JVERSION, '3.0', 'lt'))
		{
			JTable::addIncludePath(JPATH_PLATFORM . 'joomla/database/table');
		}

		$obj = new stdclass;

		$app = JFactory::getApplication();
		$article_id = $app->input->get('id', 0, 'INT');

		if (empty($app->input->get('title', '', 'STRING')))
		{
			$obj->success = false;
			$obj->message = 'Title is Missing';

			return $obj;
		}

		if (empty($app->input->get('introtext', '', 'STRING')))
		{
//			$obj->success = false;
//			$obj->message = 'Introtext is Missing';
//
//			return $obj;
			$app->input->set('introtext', '');
		}

		if (empty($app->input->get('catid', '', 'INT')))
		{
			$obj->success = false;
			$obj->message = 'Category id is Missing';

			return $obj;
		}

		$uploadConfig = new Registry();
		$uploadConfig->set('image_dest_folder', 'images');
		$uploadConfig->set('upload_maxsize', ini_get('upload_max_filesize'));
		$uploadConfig->set('action_image_file_exists', 'revision');

		try
		{
			$files = $app->input->files->get('images', [], 'ARRAY');
			$imageFilesToUpload = [];

			if (isset($files['image_intro']))
			{
				$imageFilesToUpload['image_intro'] = $files['image_intro'];
			}
			if (isset($files['image_fulltext']))
			{
				$imageFilesToUpload['image_fulltext'] = $files['image_fulltext'];
			}

			$uploadedImageFiles = $this->upload($imageFilesToUpload, $uploadConfig);
		}
		catch (\Exception $e)
		{
			$obj->success = false;
			$obj->message = $e->getMessage();

			return $obj;
		}

		if ($article_id)
		{
			$article = JTable::getInstance('Content', 'JTable', array());
			$article->load($article_id);
			$data = array(
			'title' => $app->input->get('title', '', 'STRING'),
			'alias' => $app->input->get('alias', '', 'STRING'),
			'introtext' => $app->input->get('introtext', '', 'STRING'),
			'fulltext' => $app->input->get('fulltext', '', 'STRING'),
			'state' => $app->input->get('state', '', 'INT'),
			'catid' => $app->input->get('catid', '', 'INT'),
			'publish_up' => $app->input->get('publish_up', '', 'STRING'),
			'publish_down' => $app->input->get('publish_down', '', 'STRING'),
			'language' => $app->input->get('language', '*', 'STRING')
			);

			// Bind data
			if (!$article->bind($data))
			{
				$obj->success = false;
				$obj->message = $article->getError();

				return $obj;
			}
		}
		else
		{
			$article = JTable::getInstance('content');
			$article->title = $app->input->get('title', '', 'STRING');
			$article->alias = $app->input->get('alias', '', 'STRING');
			$article->introtext = $app->input->get('introtext', '', 'STRING');
			$article->fulltext = $app->input->get('fulltext', '', 'STRING');
			$article->state = $app->input->get('state', '', 'INT');
			$article->catid = $app->input->get('catid', '', 'INT');
			$article->publish_up = $app->input->get('publish_up', '', 'STRING');
			$article->publish_down = $app->input->get('publish_down', '', 'STRING');
			$article->language = $app->input->get('language', '', 'STRING');
		}

		$articleImages = [
			'image_intro' => $uploadedImageFiles['image_intro'],
			'float_intro' => '',
			'image_intro_alt' => '',
			'image_intro_caption' => '',
			'image_fulltext' => $uploadedImageFiles['image_fulltext'],
			'float_fulltext' => '',
			'image_fulltext_alt' => '',
			'image_fulltext_caption' => ''
		];
		$article->images = json_encode($articleImages);

		// Check the data.
		if (!$article->check())
		{
			$obj->success = false;
			$obj->message = $article->getError();

			return $obj;
		}

		// Store the data.
		if (!$article->store())
		{
			$obj->success = false;
			$obj->message = $article->getError();

			return $obj;
		}

		$images = json_decode($article->images);

		foreach ($images as $key => $value)
		{
			if ($value)
			{
				$images->$key = JURI::base() . $value;
			}
		}

		$article->images = $images;
		$result = new stdClass;
		$result->results = $article;

		$obj->success = true;
		$obj->data = $result;

		return $obj;
	}

	private function teaser($article, $numChars = 120)
	{
		if (trim($article->introtext) !== '')
		{
			return $this->truncate($article->introtext, $numChars);
		}

		if (trim($article->fulltext) !== '')
		{
			return $this->truncate($article->fulltext, $numChars);
		}

		return '';
	}

	private function truncate($text, $length = 80, $ellipsis = '...', $exact = true, $html = false)
	{
		if (!function_exists('mb_strlen'))
		{
			class_exists('Multibyte');
		}

		if ($html)
		{
			if (mb_strlen(preg_replace('/<.*?>/', '', $text)) <= $length)
			{
				return $text;
			}
			$totalLength = mb_strlen(strip_tags($ellipsis));
			$openTags    = array();
			$truncate    = '';

			preg_match_all('/(<\/?([\w+]+)[^>]*>)?([^<>]*)/', $text, $tags, PREG_SET_ORDER);
			foreach ($tags as $tag)
			{
				if (!preg_match('/img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param/s', $tag[2]))
				{
					if (preg_match('/<[\w]+[^>]*>/s', $tag[0]))
					{
						array_unshift($openTags, $tag[2]);
					}
					elseif (preg_match('/<\/([\w]+)[^>]*>/s', $tag[0], $closeTag))
					{
						$pos = array_search($closeTag[1], $openTags);
						if ($pos !== false)
						{
							array_splice($openTags, $pos, 1);
						}
					}
				}
				$truncate .= $tag[1];

				$contentLength = mb_strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i', ' ', $tag[3]));
				if ($contentLength + $totalLength > $length)
				{
					$left           = $length - $totalLength;
					$entitiesLength = 0;
					if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i', $tag[3], $entities, PREG_OFFSET_CAPTURE))
					{
						foreach ($entities[0] as $entity)
						{
							if ($entity[1] + 1 - $entitiesLength <= $left)
							{
								$left--;
								$entitiesLength += mb_strlen($entity[0]);
							}
							else
							{
								break;
							}
						}
					}

					$truncate .= mb_substr($tag[3], 0, $left + $entitiesLength);
					break;
				}
				else
				{
					$truncate    .= $tag[3];
					$totalLength += $contentLength;
				}
				if ($totalLength >= $length)
				{
					break;
				}
			}
		}
		else
		{
			if (mb_strlen($text) <= $length)
			{
				return $text;
			}
			$truncate = mb_substr($text, 0, $length - mb_strlen($ellipsis));
		}
		if (!$exact)
		{
			$spacepos = mb_strrpos($truncate, ' ');
			if ($html)
			{
				$truncateCheck = mb_substr($truncate, 0, $spacepos);
				$lastOpenTag   = mb_strrpos($truncateCheck, '<');
				$lastCloseTag  = mb_strrpos($truncateCheck, '>');
				if ($lastOpenTag > $lastCloseTag)
				{
					preg_match_all('/<[\w]+[^>]*>/s', $truncate, $lastTagMatches);
					$lastTag  = array_pop($lastTagMatches[0]);
					$spacepos = mb_strrpos($truncate, $lastTag) + mb_strlen($lastTag);
				}
				$bits = mb_substr($truncate, $spacepos);
				preg_match_all('/<\/([a-z]+)>/', $bits, $droppedTags, PREG_SET_ORDER);
				if (!empty($droppedTags))
				{
					if (!empty($openTags))
					{
						foreach ($droppedTags as $closingTag)
						{
							if (!in_array($closingTag[1], $openTags))
							{
								array_unshift($openTags, $closingTag[1]);
							}
						}
					}
					else
					{
						foreach ($droppedTags as $closingTag)
						{
							$openTags[] = $closingTag[1];
						}
					}
				}
			}
			$truncate = mb_substr($truncate, 0, $spacepos);
		}
		$truncate .= $ellipsis;

		if ($html)
		{
			foreach ($openTags as $tag)
			{
				$truncate .= '</' . $tag . '>';
			}
		}

		return $truncate;
	}

	private function upload(array $files, Registry $config)
	{
		if (!$files || empty($files) || !array_filter($files))
		{
			return [];
		}

		// Instantiate the media helper
		$mediaHelper = new MediaHelper();

		// Total length of post back data in bytes.
		$contentLength = (int) $_SERVER['CONTENT_LENGTH'];

		// Maximum allowed size of post back data in MB.
		$postMaxSize = $mediaHelper->toBytes(ini_get('post_max_size'));

		// Maximum allowed size of script execution in MB.
		$memoryLimit = $mediaHelper->toBytes(ini_get('memory_limit'));

		if ($postMaxSize && $memoryLimit != -1)
		{
			$contentLengthLimit = min($postMaxSize, $memoryLimit);
		}
		else if ($postMaxSize)
		{
			$contentLengthLimit = $postMaxSize;
		}
		else
		{
			$contentLengthLimit = $contentLength;
		}

		// Check for the total size of post back data.
		if ($contentLength > $contentLengthLimit)
		{
			throw new \Exception('Submitted article is too big!', self::POST_TOO_BIG);
		}

		$folder = $config->get('image_dest_folder', 0);

		$uploadMaxSize     = $config->get('upload_maxsize', 0) * 1024 * 1024;
		$uploadMaxFileSize = $mediaHelper->toBytes(ini_get('upload_max_filesize'));

		if ($uploadMaxSize && $uploadMaxFileSize)
		{
			$imageMaxSize = min($uploadMaxSize, $uploadMaxFileSize);
		}
		else if ($uploadMaxSize)
		{
			$imageMaxSize = $uploadMaxSize;
		}
		else if ($uploadMaxFileSize)
		{
			$imageMaxSize = $uploadMaxFileSize;
		}
		else
		{
			$imageMaxSize = 0;
		}

		$uploadedFiles = [];

		foreach ($files as $fieldName => $file)
		{
			if ($file['error'] == 1 || ($imageMaxSize && $file['size'] > $imageMaxSize))
			{
				// File size exceed either 'upload_max_filesize' or 'upload_maxsize'.
				throw new \Exception('Submitted image is too big!', self::IMAGE_TOO_BIG);
			}

			$filename = \JFile::makeSafe($file['name']);
			$filename = str_replace(' ', '-', $filename);
			$folder   = \JPath::clean($folder, '/');
			$filepath = $folder . '/' . $filename;

			if (\JFile::exists($filepath))
			{
				switch ($config->get('action_image_file_exists'))
				{
					case 'keep':
						$uploadedFiles[$fieldName] = $filepath;
						continue;

					case 'revision';
						$filepath = $this->uniqueFilename($filepath);
						break;

					case 'error':
						throw new \Exception('Image already exists!', self::IMAGE_EXISTS);
				}
			}

			if (!\JFile::upload($file['tmp_name'], $filepath))
			{
				// Error in upload
				throw new \Exception('Image upload failed!', self::IMAGE_UPLOAD_FAILED);
			}

			$uploadedFiles[$fieldName] = $filepath;
		}

		return $uploadedFiles;
	}

	private function uniqueFilename($filepath, $revision = 1)
	{
		static $revisionBase;

		if (!$revisionBase)
		{
			$revisionBase = time() . '';
		}

		$filePathParts = pathinfo($filepath);
		$newFilepath = $filePathParts['dirname'] . '/' . $filePathParts['filename'] . '-' . $revisionBase . $revision;
		if (!empty($filePathParts['extension']))
		{
			$newFilepath .= '.' . $filePathParts['extension'];
		}

		if (file_exists($newFilepath))
		{
			$newFilepath = $this->uniqueFilename($filepath, ++$revision);
		}

		return  $newFilepath;
	}
}
