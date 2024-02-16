<?php

class Lindle
{
    private $api_key;

    public function __construct($api_key)
    {
        $this->api_key = $api_key;
    }

    // GETTING LINKS AND FOLDERS

    public function getUser()
    {
        $headers = ["Authorization" => "Bearer {$this->api_key}"];
        $res = json_decode($this->makeRequest("GET", "https://www.lindle.me/api/user", $headers), true);

        return [
            "id" => $res['_id'],
            "name" => $res['name'],
            "image" => $res['image'],
            "linkLimit" => $res['count'],
        ];
    }

    public function getLinks()
    {
        $headers = ["Authorization" => "Bearer {$this->api_key}"];
        $res = json_decode($this->makeRequest("GET", "https://www.lindle.me/api/links", $headers), true);
        $links = [];
        foreach ($res as $item) {
            $links[] = [
                "id" => $item["_id"],
                "name" => $item["name"],
                "url" => $item["url"],
                "folder" => $item["folder"] ?? "",
                "favourite" => $item["favourite"] ?? false,
            ];
        }
        return $links;
    }

    public function getFolders($with_links = false)
    {
        $headers = ["Authorization" => "Bearer {$this->api_key}"];
        $res = json_decode($this->makeRequest("GET", "https://www.lindle.me/api/folders", $headers), true);
        $folders = [];
        $links = $with_links ? $this->getLinks() : [];
        foreach ($res as $item) {
            if (!isset($item["public"])) {
                $item["public"] = false;
            }

            if (!isset($item["codename"])) {
                $item["codename"] = "";
            }

            $folders[] = [
                "id" => $item["_id"],
                "name" => $item["name"],
                "publicFolder" => $item["public"],
                "journeyLink" => "https://lindle.click/{$item['codename']}",
                "links" => array_filter($links, function ($link) use ($item) {
                    return $link["folder"] == $item["_id"];
                }),
            ];
        }
        return $folders;
    }

    public function getSyncedBookmarks()
    {
        $headers = ["Authorization" => "Bearer {$this->api_key}"];
        $res = json_decode($this->makeRequest("GET", "https://www.lindle.me/api/links/bookmarks/sync", $headers), true);
        $folders = array_map(function ($item) {
            return [
                "id" => $item["id"],
                "name" => $item["name"],
                "date" => $item["date"],
                "folder" => $item["folder"],
            ];
        }, $res["folders"]);

        $links = array_map(function ($item) {
            return [
                "id" => $item["id"],
                "name" => $item["name"],
                "date" => $item["date"],
                "folder" => $item["folder"],
                "url" => $item["url"],
            ];
        }, $res["links"]);

        return ["folders" => $folders, "links" => $links];
    }

    // CREATING LINKS AND FOLDERS

    public function createLink($name, $url, $folder = null, $favourite = null)
    {
        $headers = ["Authorization" => "Bearer {$this->api_key}"];
        $data = ["name" => $name, "url" => $url, "folder" => $folder, "favourite" => $favourite];
        $res = json_decode($this->makeRequest("POST", "https://www.lindle.me/api/links", $headers, json_encode($data)), true);
        $item = $res["link"] ?? [];
        $link = [
            "id" => $item["_id"] ?? "",
            "name" => $item["name"] ?? "",
            "url" => $item["url"] ?? "",
            "folder" => $item["folder"] ?? "",
            "favourite" => $item["favourite"] ?? false,
        ];
        return ["message" => $res["message"] ?? "", "result" => $res["result"] ?? "", "link" => $link];
    }

    public function createFolder($name, $public_folder = null)
    {
        $headers = ["Authorization" => "Bearer {$this->api_key}"];
        $data = ["name" => $name, "public" => $public_folder];
        $res = json_decode($this->makeRequest("POST", "https://www.lindle.me/api/folders", $headers, json_encode($data)), true);
        return $res;
    }

    // UPDATING LINKS AND FOLDERS

    public function updateLink($link_id, $name = null, $url = null, $folder = null, $favourite = null)
    {
        $headers = ["Authorization" => "Bearer {$this->api_key}"];
        $data = ["name" => $name, "url" => $url, "folder" => $folder, "favourite" => $favourite];
        $res = json_decode($this->makeRequest("PATCH", "https://www.lindle.me/api/links/{$link_id}", $headers, json_encode($data)), true);
        return $res;
    }

    public function updateFolder($folder_id, $name = null, $public_folder = null)
    {
        $headers = ["Authorization" => "Bearer {$this->api_key}"];
        $data = ["name" => $name, "public" => $public_folder];
        $res = json_decode($this->makeRequest("PATCH", "https://www.lindle.me/api/folders/{$folder_id}", $headers, json_encode($data)), true);
        return $res;
    }

    // DELETE and REMOVAL

    public function deleteLink($link_id)
    {
        $headers = ["Authorization" => "Bearer {$this->api_key}"];
        $res = json_decode($this->makeRequest("DELETE", "https://www.lindle.me/api/links/{$link_id}", $headers), true);
        return $res;
    }

    public function deleteFolder($folder_id)
    {
        $headers = ["Authorization" => "Bearer {$this->api_key}"];
        $res = json_decode($this->makeRequest("DELETE", "https://www.lindle.me/api/folders/{$folder_id}", $headers), true);
        return $res;
    }

    private function makeRequest($method, $url, $headers = [], $data = null)
    {
        $curl = curl_init();

        // Disable SSL (Not Recommended) - https://cheapsslsecurity.com/blog/ssl-certificate-problem-unable-to-get-local-issuer-certificate/
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);


        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);

        if (!empty($headers)) {
            $curl_headers = [];
            foreach ($headers as $key => $value) {
                $curl_headers[] = "{$key}: {$value}";
            }
            curl_setopt($curl, CURLOPT_HTTPHEADER, $curl_headers);
        }

        if ($method === "POST" || $method === "PATCH") {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            curl_setopt($curl, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        }

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            echo 'Curl error: ' . curl_error($curl);
        }

        curl_close($curl);
        return $response;
    }
}


