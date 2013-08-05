<?php

require '../bootstrap.php';

// Prepare app

$app = new \Slim\Slim(require '../config.php');

// Define routes

$app->get('/', function () use ($app) {

    $app->render('home.tpl.php', array(
        'action' => $app->urlFor('home'),
    ));

})->name('home');

$app->post('/', function () use ($app) {
    $url = trim($app->request()->post('url'));

    if ($url && str_start_with(JIANSHU, $url)) {
        if (str_start_with(JIANSHU_RECOMMENDATIONS_NOTES, $url)) {
            $app->redirect($app->urlFor('feeds.recommendations'));
        } elseif (str_start_with(JIANSHU_COLLECTIONS_ROOT, $url)) {
            $app->redirect($app->urlFor('feeds.collections', array('id' => substr($url, strlen(JIANSHU_COLLECTIONS_ROOT)))));
        } elseif(str_start_with(JIANSHU_NOTEBOOKS_ROOT, $url) && str_end_with('/list', $url)) {
            $app->redirect($app->urlFor('feeds.notebooks', array('id' => intval(substr($url, strlen(JIANSHU_NOTEBOOKS_ROOT))))));
        } elseif (str_start_with(JIANSHU_USERS_ROOT, $url)) {
            $app->redirect($app->urlFor('feeds.users', array('id' => substr($url, strlen(JIANSHU_USERS_ROOT)))));
        } else {
            throw new Exception('Invalid Jianshu URL.');
        }
    } else {
        throw new Exception('Invalid Jianshu URL.');
    }
});

$app->get('/about', function () use ($app) {

    echo 'About Jianshu RSS!';

})->name('pages.about');

$app->get('/feeds/recommendations/notes', function () use ($app) {

    // List
    $recommendations_notes_file_path = CACHE_ROOT . DS . 'recommendations-notes.html';
    if (file_exists($recommendations_notes_file_path) && filemtime($recommendations_notes_file_path) > time() - 900) {
        $html_str = file_get_contents($recommendations_notes_file_path);
    } else {
        $html_str = file_get_contents(JIANSHU_RECOMMENDATIONS_NOTES);
        if ($html_str === false) {
            throw new Exception('Can not fetch Jianshu note\'s content with uri: ' . JIANSHU_RECOMMENDATIONS_NOTES);
        }
        file_put_contents($recommendations_notes_file_path, $html_str);
    }

    $html = str_get_html($html_str);
    $meta = array(
        'title' => $html->find('.page-title', 0)->plaintext,
        'link' => JIANSHU_RECOMMENDATIONS_NOTES,
        'description' => '简书是一款属于写作者的笔记本, 我们致力于提供一个简洁而优雅的环境让你专注于书写。',
    );
    $notes = array();
    foreach ($html->find('.thumbnail') as $element) {
        $note = new stdClass();
        $note->title = trim($element->find('h4', 0)->plaintext);
        $note->uri = $element->href;

        $notes[] = $note;
    }
    // Notes
    foreach ($notes as $note) {
        $note_file_path = NOTES_ROOT . DS . md5($note->uri) . '.html';
        if (file_exists($note_file_path)) {
            $html_str = file_get_contents($note_file_path);
        } else {
            $html_str = file_get_contents(JIANSHU . $note->uri);
            if ($html_str === false) {
                throw new Exception('Can not fetch Jianshu note\'s content with uri: ' . $note->uri);
            }
            file_put_contents($note_file_path, $html_str);
        }

        $html = str_get_html($html_str);

        $note->author = trim($html->find('.people .author', 0)->plaintext);
        $note->author_uri = $html->find('.people .author', 0)->href;
        $note->book = trim($html->find('.article .article-info a', 0)->plaintext);
        $note->book_uri = $html->find('.article .article-info a', 0)->href;
        $note->author_uri = $html->find('.people .author', 0)->href;
        $note->body = $html->find('.show-content', 0)->innertext;
        $note->created = trim($html->find('.article-info p', 0)->find('text', 3));
    }

    // Feed
    $feed = new \Suin\RSSWriter\Feed();
    $channel = new \Suin\RSSWriter\Channel();
    $channel->title($meta['title'])
        ->url($meta['link'])
        ->description($meta['description'])
        ->appendTo($feed);
    foreach ($notes as $note) {
        $item = new \Suin\RSSWriter\Item();
        $item = $item->title($note->title . ' by ' . $note->author . ' · ' . $note->book)
            ->url(JIANSHU . $note->uri)
            ->description($note->body)
            ->pubDate(strtotime($note->created))
            ->appendTo($channel);
    }

    $res = $app->response();
    $res->status(200);
    $res->header('Content-Type', 'application/rss+xml; charset=utf-8');
    $res->header('X-Powered-By', 'Slim');
    $res->body($feed->render());

    return $res;
})->name('feeds.recommendations');

$app->get('/feeds/collections/:id', function ($id) use ($app) {
    $html_str = file_get_contents(JIANSHU_COLLECTIONS_ROOT . $id);
    $html = str_get_html($html_str);

    $meta = array(
        'title' => trim($html->find('.header .title', 0)->plaintext),
        'link' => JIANSHU_COLLECTIONS_ROOT . $id,
        'description' => trim($html->find('.header .description', 0)->plaintext),
    );

    $notes = array();
    foreach ($html->find('.thumbnail') as $element) {
        $note = new stdClass();
        $note->uri = $element->href;
        $note->title = trim($element->find('h4', 0)->plaintext);

        $notes[] = $note;
    }

    // Notes
    foreach ($notes as $note) {
        $note_file_path = NOTES_ROOT . DS . md5($note->uri) . '.html';
        if (file_exists($note_file_path)) {
            $html_str = file_get_contents($note_file_path);
        } else {
            $html_str = file_get_contents(JIANSHU . $note->uri);
            if ($html_str === false) {
                throw new Exception('Can not fetch Jianshu note\'s content with uri: ' . $note->uri);
            }
            file_put_contents($note_file_path, $html_str);
        }

        $html = str_get_html($html_str);

        $note->author = trim($html->find('.people .author', 0)->plaintext);
        $note->author_uri = $html->find('.people .author', 0)->href;
        $note->book = trim($html->find('.article .article-info a', 0)->plaintext);
        $note->book_uri = $html->find('.article .article-info a', 0)->href;
        $note->author_uri = $html->find('.people .author', 0)->href;
        $note->body = $html->find('.show-content', 0)->innertext;
        $note->created = trim($html->find('.article-info p', 0)->find('text', 3));
    }

    // Feed
    $feed = new \Suin\RSSWriter\Feed();
    $channel = new \Suin\RSSWriter\Channel();
    $channel->title($meta['title'])
        ->url($meta['link'])
        ->description($meta['description'])
        ->appendTo($feed);
    foreach ($notes as $note) {
        $item = new \Suin\RSSWriter\Item();
        $item = $item->title($note->title . ' by ' . $note->author . ' · ' . $note->book)
            ->url(JIANSHU . $note->uri)
            ->description($note->body)
            ->pubDate(strtotime($note->created))
            ->appendTo($channel);
    }

    $res = $app->response();
    $res->status(200);
    $res->header('Content-Type', 'application/rss+xml; charset=utf-8');
    $res->header('X-Powered-By', 'Slim');
    $res->body($feed->render());

    return $res;
})->name('feeds.collections')
    ->conditions(array('id' => '[a-zA-Z0-9]{6}'));

$app->get('/feeds/notebooks/:id', function ($id) use ($app) {
    $html_str = file_get_contents(JIANSHU_NOTEBOOKS_ROOT . $id . '/list');
    $html = str_get_html($html_str);

    $meta = array(
        'title' => trim($html->find('.notebook-header .title', 0)->plaintext),
        'link' => JIANSHU_NOTEBOOKS_ROOT . $id . '/list',
        'description' => 'by ' . trim($html->find('.notebook-header .author', 0)->plaintext),
    );

    $notes = array();
    foreach ($html->find('.thumbnail') as $element) {
        $note = new stdClass();
        $note->uri = $element->href;
        $note->title = trim($element->find('h4', 0)->plaintext);

        $notes[] = $note;
    }

    // Notes
    foreach ($notes as $note) {
        $note_file_path = NOTES_ROOT . DS . md5($note->uri) . '.html';
        if (file_exists($note_file_path)) {
            $html_str = file_get_contents($note_file_path);
        } else {
            $html_str = file_get_contents(JIANSHU . $note->uri);
            if ($html_str === false) {
                throw new Exception('Can not fetch Jianshu note\'s content with uri: ' . $note->uri);
            }
            file_put_contents($note_file_path, $html_str);
        }

        $html = str_get_html($html_str);

        $note->author = trim($html->find('.people .author', 0)->plaintext);
        $note->author_uri = $html->find('.people .author', 0)->href;
        $note->book = trim($html->find('.article .article-info a', 0)->plaintext);
        $note->book_uri = $html->find('.article .article-info a', 0)->href;
        $note->author_uri = $html->find('.people .author', 0)->href;
        $note->body = $html->find('.show-content', 0)->innertext;
        $note->created = trim($html->find('.article-info p', 0)->find('text', 3));
    }

    // Feed
    $feed = new \Suin\RSSWriter\Feed();
    $channel = new \Suin\RSSWriter\Channel();
    $channel->title($meta['title'])
        ->url($meta['link'])
        ->description($meta['description'])
        ->appendTo($feed);
    foreach ($notes as $note) {
        $item = new \Suin\RSSWriter\Item();
        $item = $item->title($note->title . ' by ' . $note->author . ' · ' . $note->book)
            ->url(JIANSHU . $note->uri)
            ->description($note->body)
            ->pubDate(strtotime($note->created))
            ->appendTo($channel);
    }

    $res = $app->response();
    $res->status(200);
    $res->header('Content-Type', 'application/rss+xml; charset=utf-8');
    $res->header('X-Powered-By', 'Slim');
    $res->body($feed->render());

    return $res;
})->name('feeds.notebooks')
    ->conditions(array('id' => '[1-9][0-9]*'));

$app->get('/feeds/users/:id', function ($id) use ($app) {
    $html_str = file_get_contents(JIANSHU_USERS_ROOT . $id);
    $html = str_get_html($html_str);

    $meta = array(
        'title' => trim($html->find('.people .name', 0)->plaintext),
        'link' => JIANSHU_USERS_ROOT . $id,
        'description' => trim($html->find('.people .about .intro', 0)->plaintext),
    );

    $notes = array();
    foreach ($html->find('.recent-post .title') as $element) {
        $note = new stdClass();
        $note->uri = $element->href;
        $note->title = trim($element->plaintext);

        $notes[] = $note;
    }

    // Notes
    foreach ($notes as $note) {
        $note_file_path = NOTES_ROOT . DS . md5($note->uri) . '.html';
        if (file_exists($note_file_path)) {
            $html_str = file_get_contents($note_file_path);
        } else {
            $html_str = file_get_contents(JIANSHU . $note->uri);
            if ($html_str === false) {
                throw new Exception('Can not fetch Jianshu note\'s content with uri: ' . $note->uri);
            }
            file_put_contents($note_file_path, $html_str);
        }

        $html = str_get_html($html_str);

        $note->author = trim($html->find('.people .author', 0)->plaintext);
        $note->author_uri = $html->find('.people .author', 0)->href;
        $note->book = trim($html->find('.article .article-info a', 0)->plaintext);
        $note->book_uri = $html->find('.article .article-info a', 0)->href;
        $note->author_uri = $html->find('.people .author', 0)->href;
        $note->body = $html->find('.show-content', 0)->innertext;
        $note->created = trim($html->find('.article-info p', 0)->find('text', 3));
    }

    // Feed
    $feed = new \Suin\RSSWriter\Feed();
    $channel = new \Suin\RSSWriter\Channel();
    $channel->title($meta['title'])
        ->url($meta['link'])
        ->description($meta['description'])
        ->appendTo($feed);
    foreach ($notes as $note) {
        $item = new \Suin\RSSWriter\Item();
        $item = $item->title($note->title . ' by ' . $note->author . ' · ' . $note->book)
            ->url(JIANSHU . $note->uri)
            ->description($note->body)
            ->pubDate(strtotime($note->created))
            ->appendTo($channel);
    }

    $res = $app->response();
    $res->status(200);
    $res->header('Content-Type', 'application/rss+xml; charset=utf-8');
    $res->header('X-Powered-By', 'Slim');
    $res->body($feed->render());

    return $res;

})->name('feeds.users')
    ->conditions(array('id' => '[a-zA-Z0-9]{6}'));

// Run app
$app->run();