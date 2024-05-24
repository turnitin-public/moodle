<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace ltixservice_assetprocessor\local\resources;

use core_ltix\local\ltiservice\resource_base;
use ltixservice_assetprocessor\local\service\assetprocessor;
use stdClass;

/**
 * This file contains a class definition for the Asset service (fetch file).
 *
 * @package    ltixservice_assetprocessor
 * @copyright  Catalyst IT
 * @author     Dan Marsden
 * @since      Moodle 4.5
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assetservice extends resource_base {
    /**
     * Class constructor.
     *
     * @param \ltixservice_assetprocessor\local\service\assetprocessor $service Service instance
     */
    public function __construct($service) {

        parent::__construct($service);
        $this->id = 'assetservice';
        $this->template = '/api/lti/assetservice/{asset_id}';
        $this->formats[] = 'application/json';
        $this->methods[] = self::HTTP_POST;
    }

    /**
     * Execute the request for this resource.
     *
     * @param \core_ltix\local\ltiservice\response $response  Response object for this request.
     */
    public function execute($response) {
        $params = $this->parse_template();
        $pathnamehash = $params['asset_id'];

        if (!$this->check_tool(null, $response->get_request_data(), array(assetprocessor::SCOPE_ASSET_READ))) {
            $response->set_code(403);
            return;
        }
        $json = $this->get_file_metadata($pathnamehash);
        if (empty($json)) {
            $response->set_code(404);
            return;
        }

        $response->set_content_type($this->formats[0]);
        $response->set_body($json);
    }

    /**
     * Get json response for LTI fetch file.
     *
     * @param string $pathnamehash
     * @return string|null
     */
    public static function get_file_metadata($pathnamehash) {
        $fs = get_file_storage();
        $file = $fs->get_file_by_hash($pathnamehash);
        if (empty($file)) {
            return null;
        }

        $url = self::make_download_url($pathnamehash);

        $data = new stdClass();
        $data->asset_id = $pathnamehash;
        $data->url = $url->out();
        $data->title = $file->get_filename();
        $data->filename = $file->get_filename();
        $data->sha256_checksum = $file->get_contenthash();
        $data->timestamp = $file->get_timecreated();
        $data->size = $file->get_filesize();
        $data->conent_type = $file->get_mimetype();

        return json_encode($data);
    }

    /**
     * Custom plugin file url that allows oauth connected LTI to request file.
     * We use pathnamehash because only known pathnamehash values should be allowed.
     *
     * @param string $pathnamehash
     * @return moodle_url
     */
    private static function make_download_url($pathnamehash) {
        global $CFG;
        $urlbase = "$CFG->wwwroot/ltix/service/assetprocessor/downloadfile.php";
        return new \moodle_url($urlbase, ['pathnamehash' => $pathnamehash]);
    }
}
