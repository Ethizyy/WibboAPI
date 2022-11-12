<?php
namespace App\Controller\Admin;

use App\Controller\DefaultController;
use App\Models\RoleplayItem;
use App\Models\User;
use Slim\Http\Request;
use Slim\Http\Response;
use Exception;
use App\Helper\Utils;

class RoleplayItemController extends DefaultController
{
    public function get(Request $request, Response $response, array $args): Response
    {
        $input = $request->getParsedBody();
        $userId = $input['decoded']->sub;

        $user = User::where('id', $userId)->select('rank')->first();
        if(!$user) throw new Exception('disconnect', 401);
                
        if ($user->rank < 6) {
            throw new Exception('permission', 403);
        }

        $items = RoleplayItem::get();

		$message = [
			'items' => $items
        ];

        return $this->jsonResponse($response, $message);
    }
    
    public function post(Request $request, Response $response, array $args): Response
    {
        $input = $request->getParsedBody();
        $userId = $input['decoded']->sub;

        $data = json_decode(json_encode($input), false);

        $this->requireData($data, ['name', 'desc', 'price', 'value', 'allowstack', 'type', 'category']);

        $user = User::where('id', $userId)->select('rank')->first();
        if(!$user) throw new Exception('disconnect', 401);
                
        if ($user->rank < 8) {
            throw new Exception('permission', 403);
        }

        $files = $request->getUploadedFiles();
        $nom = $data->name;
        $desc = $data->desc;
        $price = $data->price;
        $value = $data->value;
        $allowstack = $data->allowstack;
        $type = $data->type;
        $category = $data->category;

        if (empty($files['file']) || $nom == "" || $desc == "" || $price == "" || $value == "" || $allowstack == "" || $type == "" || $category == "") {
            throw new Exception('error !', 400);
        }

        if (!is_numeric($price) || !is_numeric($value) || !is_numeric($allowstack)) {
            throw new Exception('error', 400);
        }

        $uploadFileName = $files['file']->getClientFilename();

        $extension_upload = substr(strrchr($uploadFileName, '.'), 1);
        if ($extension_upload != 'png') {
            throw new Exception('error', 400);
        }

        $size = getimagesize($files['file']->file);
        if ($size[1] != 40 || $size[0] != 40) {
            throw new Exception('error', 400);
        }

        $data = array(
            array(
                'action' => 'upload',
                'path' => 'items/' . $nom . '.png',
                'data' => base64_encode(file_get_contents($files['file']->file))
            )
        );
    
        if (!Utils::uploadApi("cdn", $data)) {
            throw new Exception('error', 400);
        }

        $id = RoleplayItem::insertGetId([
            'name' => $nom,
            'desc' => $desc,
            'price' => $price,
            'type' => $type,
            'value' => $value,
            'allowstack' => $allowstack,
            'category' => $category,
        ]);

        return $this->jsonResponse($response, []);
    }

    public function patch(Request $request, Response $response, array $args): Response
    {
        $input = $request->getParsedBody();
        $userId = $input['decoded']->sub;

        $data = json_decode(json_encode($input), false);

        $this->requireData($data, ['desc', 'price', 'value', 'allowstack', 'type', 'category']);

        $user = User::where('id', $userId)->select('rank')->first();
        if(!$user) throw new Exception('disconnect', 401);
                
        if ($user->rank < 8) {
            throw new Exception('permission', 403);
        }

        $id = $args['id'];

        $desc = $data->desc;
        $price = $data->price;
        $value = $data->value;
        $allowstack = $data->allowstack;
        $type = $data->type;
        $category = $data->category;

        if (!isset($desc) || !isset($price) || !isset($value) || !isset($allowstack) || !isset($type) || !isset($category)) {
            throw new Exception('error !', 400);
        }

        if (!is_numeric($id) || !is_numeric($price) || !is_numeric($value) || !is_numeric($allowstack)) {
            throw new Exception('error', 400);
        }

        $item = RoleplayItem::where('id', $id)->first();
        if (!$item) {
            throw new Exception('error', 400);
        }

        RoleplayItem::where('id', $id)->update([
            'desc' => $desc,
            'price' => $price,
            'type' => $type,
            'value' => $value,
            'allowstack' => $allowstack,
            'category' => $category,
        ]);

        return $this->jsonResponse($response, []);
    }
}