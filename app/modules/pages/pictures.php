<?php

if (! is_user()) {
    abort(403, 'Чтобы загружать фотографии необходимо авторизоваться');
}

switch ($action):
/**
 * Главная страница
 */
case 'index':

    if (Request::isMethod('post')) {

        $newName = uniqid();
        $token   = check(Request::input('token'));

        $validation = new Validation();
        $validation->addRule('equal', [$token, $_SESSION['token']], ['photo' => 'Неверный идентификатор сессии, повторите действие!']);

        $handle = upload_image($_FILES['photo'], setting('filesize'), setting('fileupfoto'), $newName);
        if (! $handle) {
            $validation -> addError(['photo' => 'Не удалось загрузить фотографию!']);
        }

        if ($validation->run()) {
            //-------- Удаляем старую фотку и аватар ----------//
            $user = User::find(getUserId());

            if ($user['picture']) {
                unlink_image('uploads/photos/', $user['picture']);
                unlink_image('uploads/avatars/', $user['avatar']);

                $user->picture = null;
                $user->avatar = null;
                $user->save();
            }

            //-------- Генерируем аватар ----------//
            $handle->process(HOME.'/uploads/photos/');

            if ($handle->processed) {
                $picture = $handle->file_dst_name;

                $handle->file_new_name_body = $newName;
                $handle->image_resize = true;
                $handle->image_ratio_crop = true;
                $handle->image_y = 48;
                $handle->image_x = 48;
                $handle->image_watermark = false;
                $handle->image_convert = 'png';
                $handle->file_overwrite = true;

                $handle->process(HOME . '/uploads/avatars/');
                $avatar = $handle->file_dst_name;

                if ($handle->processed) {

                    $user = User::find(getUserId());
                    $user->picture = $picture;
                    $user->avatar = $avatar;
                    $user->save();

                    save_avatar();
                }

                setFlash('success', 'Фотография успешно загружена!');
                redirect('/profile');
            } else {
                $validation -> addError(['photo' => $handle->error]);
            }
        }

        setInput(Request::all());
        setFlash('danger', $validation->getErrors());
    }

    $user = User::where('login', getUsername())->first();
    view('pages/picture', compact('user'));
break;


/**
 * Удаление фото и аватара
 */
case 'delete':

    $token = check(Request::input('token'));

    $validation = new Validation();
    $validation->addRule('equal', [$token, $_SESSION['token']], ['photo' => 'Неверный идентификатор сессии, повторите действие!']);

    $user = User::find(getUserId());
    if (! $user || ! $user['picture']) {
        $validation -> addError('Фотографии для удаления не существует!');
    }

    if ($validation->run()) {

        unlink_image('uploads/photos/', $user['picture']);
        unlink_image('uploads/avatars/', $user['avatar']);

        $user->picture = null;
        $user->avatar = null;
        $user->save();

        setFlash('success', 'Фотография успешно удалена!');
    } else {
        setFlash('danger', $validation->getErrors());
    }

    redirect('/profile');

break;
endswitch;
