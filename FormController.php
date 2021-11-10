<?php

namespace App\Http\Controllers;

use App\Library\Helper;
use Auth;
use Config;
use DB;
use File;
use Illuminate\Http\Request;
use Mail;
use Response;
use Session;

class FormController extends Controller
{
    /**
     * Display index page with all categories and form created using default category coming from database
     */

    public $tag_ids = [];
    public $form_mode = 'view';
    public $data_mode = 1;
    public $data_exists = false;
    public $key = 'abcd';
    public $user_id;
    public $fields = [];
    public $values = [];

    public function __construct()
    {
        header('Access-Control-Allow-Origin: *');
    }

    public function index($id = null)
    {

        if (Session::get('user')) {
            if ($id != null) {
                $permid = intval(base64_decode($id));

                if ($permid != 0) {
                    $user = DB::select('CALL sp_get_userpermission_by_id(?)', [$permid]);
                    if (isset($user[0])) {
                        $this->user_id = $user[0]->org_userid;
                        $user_id = $this->user_id;

                        $access = explode('-', $user[0]->user_access_type);
                        $accesstype = trim(str_replace(' ', '', str_replace($access[0], '', $user[0]->user_access_type)));
                        $type = trim($access[0]);

                        if ($type == 'ICE') {
                            $data = json_decode($this->fetchAccessCategories('ICE'));
                            $categories = $data->categories;
                            $subcategories = $data->subcategories;
                            $selected_cat = 'ICE';
                            $emergency = 1;

                        } else {

                            $data = json_decode($this->fetchCategories());
                            $categories = $data->categories;
                            $subcategories = $data->subcategories;
                            $selected_cat = 'General';
                            $emergency = 0;
                        }

                        //call index view with data to be used
                        return view('records', compact('categories', 'subcategories', 'user_id', 'accesstype', 'emergency', 'selected_cat'));

                    } else {
                        Session::put('record_error', 'Invalid Access');
                        return redirect('/access-control');
                    }
                } else {
                    Session::put('record_error', 'Invalid Access');
                    return redirect('/access-control');
                }
            } else {
                $this->user_id = (Session::get('user')) ? (Session::get('user')->user_id) : 1;
                $user_id = $this->user_id;
                $data = json_decode($this->fetchCategories());
                $categories = $data->categories;
                $subcategories = $data->subcategories;
                $emergency = 0;
                if (!Session::has('user_id')) {
                    Session::put('user_id', $this->user_id);
                }
                //call index view with data to be used
                return view('records', compact('categories', 'subcategories', 'user_id', 'emergency'));
            }
        } else {
            return redirect('/');
        }
    }

    // temporary function to change user_id
    public function changeUser(Request $request)
    {
        $this->user_id = $request->user_id;
        Session::put('user_id', $this->user_id);
        echo $this->user_id;
    }

    /**
     * function to fetch categories from database using procedure
     */
    public function fetchCategories()
    {
        $data = DB::select('CALL sp_get_all_data_tag_categories_by_category_where_active(?,?)', [Session::get('site_lang'), Session::get('user')->user_level_id]);
        $categories = [];
        $subcategories = [];

        //loop to create category and subcategory array from the data fetched
        foreach ($data as $value) {

            $value->original_category = $value->tag_category;
            $value->original_sub_category = $value->tag_sub_category;
            // check site language and if not english, replace the tag_category and tag_subcategory values with the updated value respect to the language
            if (Session::get('site_lang') != 26 && isset($value->category_translation_name) && $value->category_translation_name != null) {
                $value->tag_category = $value->category_translation_name;
            }
            if (Session::get('site_lang') != 26 && isset($value->sub_category_translation_name) && $value->sub_category_translation_name != null) {
                $value->tag_sub_category = $value->sub_category_translation_name;
            }

            //condition to check category already added into the array (effective - in case of subcategories, only single entry needs to be there for parent category)
            if (!isset($categories[$value->category_display_order])) {
                $categories[$value->category_display_order] = $value;

                //if category has subcategory, add array in subcategory element with parent category display order as key
                if (!empty($value->tag_sub_category)) {
                    $subcategories[$value->category_display_order] = [];
                    // add subcategory listing inside the array
                    $subcategories[$value->category_display_order][$value->sub_category_display_order] = $value;
                }
            } else {
                if (!empty($value->tag_sub_category)) {
                    $subcategories[$value->category_display_order][$value->sub_category_display_order] = $value;
                }
            }

        }
        return json_encode(['categories' => $categories, 'subcategories' => $subcategories]);
    }

    /**
     * function to fetch categories from database using procedure
     */
    public function fetchAccessCategories($cat)
    {
        $data = DB::select('CALL sp_get_categories_by_access(?,?)', [Session::get('site_lang'), $cat]);
        $categories = [];
        $subcategories = [];

        //loop to create category and subcategory array from the data fetched
        foreach ($data as $value) {

            $value->original_category = $value->tag_category;
            $value->original_sub_category = $value->tag_sub_category;
            // check site language and if not english, replace the tag_category and tag_subcategory values with the updated value respect to the language
            if (Session::get('site_lang') != 26 && isset($value->category_translation_name) && $value->category_translation_name != null) {
                $value->tag_category = $value->category_translation_name;
            }
            if (Session::get('site_lang') != 26 && isset($value->sub_category_translation_name) && $value->sub_category_translation_name != null) {
                $value->tag_sub_category = $value->sub_category_translation_name;
            }

            //condition to check category already added into the array (effective - in case of subcategories, only single entry needs to be there for parent category)
            if (!isset($categories[$value->category_display_order])) {
                $categories[$value->category_display_order] = $value;

                //if category has subcategory, add array in subcategory element with parent category display order as key
                if (!empty($value->tag_sub_category)) {
                    $subcategories[$value->category_display_order] = [];
                    // add subcategory listing inside the array
                    $subcategories[$value->category_display_order][$value->sub_category_display_order] = $value;
                }
            } else {
                if (!empty($value->tag_sub_category)) {
                    $subcategories[$value->category_display_order][$value->sub_category_display_order] = $value;
                }
            }

        }
        return json_encode(['categories' => $categories, 'subcategories' => $subcategories]);
    }

    /**
     * function to fetch form data of the selected category from database using procedure
     */
    public function fetchFormData(Request $request)
    {
        $category = $request->category;
        $subcategory = empty($request->subcategory) ? '' : $request->subcategory;

        if ($request->emergency == 0) {
            $data = DB::select('CALL sp_get_all_data_tag_definitions_by_tag_category(?,?,?,?)', [$category, $subcategory, $this->data_mode, Session::get('site_lang')]);
        } elseif ($request->emergency == 1) {
            $data = DB::select('CALL sp_get_ice_data_tag_definitions(?)', [Session::get('site_lang')]);
        }

        $fields = [];
        $orders = [];

        //create order array with orders as key and tag id as value.
        //create another array with tag id as key and complete object as its value.
        foreach ($data as $key => $value) {

            // check site language and if not english, replace the form field and form field values with the updated value respect to the language
            if (Session::get('site_lang') != 26 && isset($value->tag_display_translation_name) && $value->tag_display_translation_name != null) {
                $value->tag_display_name = $value->tag_display_translation_name;
            }
            if (Session::get('site_lang') != 26 && isset($value->tag_default_translation_value) && $value->tag_default_translation_value != null) {
                $value->tag_default_value = $value->tag_default_translation_value;
            }
            if (Session::get('site_lang') != 26 && isset($value->tag_translation_description) && $value->tag_translation_description != null) {
                $value->tag_description = $value->tag_translation_description;
            }
            if (Session::get('site_lang') != 26 && isset($value->tag_display_translation_hint) && $value->tag_display_translation_hint != null) {
                $value->tag_display_hint = $value->tag_display_translation_hint;
            }
            if (Session::get('site_lang') != 26 && isset($value->tag_public_translation_name) && $value->tag_public_translation_name != null) {
                $value->tag_public_note = $value->tag_public_translation_name;
            }
            if (Session::get('site_lang') != 26 && isset($value->tag_private_translation_name) && $value->tag_private_translation_name != null) {
                $value->tag_private_note = $value->tag_private_translation_name;
            }

            array_push($this->tag_ids, $value->tag_id);
            if ($request->emergency == 0) {
                //split order with letters and alphabets.
                $p = preg_split('/(?<=[0-9])(?=[a-zA-Z]+)/i', $value->tag_display_order);
                if (count($p) > 1) {
                    //if order contains alphabets, make another array for that row.
                    if (!isset($orders[$p[0]])) {
                        $orders[$p[0]] = array();
                    }
                    $t = strtoupper($p[1]);

                    // in case of same display order of multiple category fields (emergency data), append number with alphabet until found unique
                    if (isset($orders[$p[0]][$t])) {
                        $temp = $t;
                        $i = 1;
                        do {
                            $temp = $temp . ($i++);
                        } while (isset($orders[$p[0]][$temp]));
                        $t = $temp;
                    }

                    $orders[$p[0]][$t] = $value->tag_id;
                    //sort internal array with alphabets
                    uksort(
                        $orders[$p[0]],
                        function ($a, $b) {
                            sscanf($a, '%[A-Z]%d', $ac, $ar);
                            sscanf($b, '%[A-Z]%d', $bc, $br);
                            return ($ac == '' || $bc == '') ? ($a >= $b) : (($ar == $br) ? $ac <=> $bc : $ar <=> $br);
                        }
                    );
                } else {
                    if (!isset($orders[$p[0]])) {
                        $orders[$p[0]] = array();
                    }
                    array_push($orders[$p[0]], $value->tag_id);

                    uksort(
                        $orders[$p[0]],
                        function ($a, $b) {
                            sscanf($a, '%[A-Z]%d', $ac, $ar);
                            sscanf($b, '%[A-Z]%d', $bc, $br);
                            return ($ac == '' || $bc == '') ? ($a >= $b) : (($ar == $br) ? $ac <=> $bc : $ar <=> $br);
                        }
                    );
                }
            } else {
                //split order with letters and alphabets.
                $p = preg_split('/(?<=[0-9])(?=[a-zA-Z]+)/i', $value->tag_ice_display_order);
                if (count($p) > 1) {
                    //if order contains alphabets, make another array for that row.
                    if (!isset($orders[$p[0]])) {
                        $orders[$p[0]] = array();
                    }
                    $t = strtoupper($p[1]);

                    // in case of same display order of multiple category fields (emergency data), append number with alphabet until found unique
                    if (isset($orders[$p[0]][$t])) {
                        $temp = $t;
                        $i = 1;
                        do {
                            $temp = $temp . ($i++);
                        } while (isset($orders[$p[0]][$temp]));
                        $t = $temp;
                    }

                    $orders[$p[0]][$t] = $value->tag_id;
                    //sort internal array with alphabets
                    uksort(
                        $orders[$p[0]],
                        function ($a, $b) {
                            sscanf($a, '%[A-Z]%d', $ac, $ar);
                            sscanf($b, '%[A-Z]%d', $bc, $br);
                            return ($ac == '' || $bc == '') ? ($a >= $b) : (($ar == $br) ? $ac <=> $bc : $ar <=> $br);
                        }
                    );
                } else {
                    if (!isset($orders[$p[0]])) {
                        $orders[$p[0]] = array();
                    }
                    array_push($orders[$p[0]], $value->tag_id);

                    uksort(
                        $orders[$p[0]],
                        function ($a, $b) {
                            sscanf($a, '%[A-Z]%d', $ac, $ar);
                            sscanf($b, '%[A-Z]%d', $bc, $br);
                            return ($ac == '' || $bc == '') ? ($a >= $b) : (($ar == $br) ? $ac <=> $bc : $ar <=> $br);
                        }
                    );
                }
            }
            $fields[$value->tag_id] = $value;

        }
        // if ($request->emergency == 0) {
        //sort array by keys(display order)
        ksort($orders);
        // }

        if ($request->emergency == 0) {
            //move element with 0 display order from top to bottom
            if (isset($orders[0])) {
                $zeroOrder = $orders[0];
                unset($orders[0]);
                $orders[0] = $zeroOrder;
            }
        } else {
            //move element with 0 display order from top to bottom
            if (isset($orders[0])) {
                $zeroOrder = $orders[0];
                unset($orders[0]);
                $orders[0] = $zeroOrder;
            }
        }

        $this->fields = $fields;

        if (count($data) > 0) {
            // fetch tag values from database
            $field_values = $this->fetchFormValues($request->user_id);

            // customize value array with tag_id as key and value as value
            foreach ($field_values as $key => $value) {
                if (isset($value->tag_id)) {
                    $this->values[$value->tag_id] = $value->value;
                }
            }
        }

        $field_exists = (count($data) > 0);

        $html = '';
        if (stripos($request->category, 'contact') !== false) {
            $html .= '
				<div class="row">
					<div class="form-group col-md-12">
						<div class="d-flex flex-column flex-lg-row">
                			<div class="mr-3 d-flex align-items-center mt-4">
                                <label class="mr-2">' . strtoupper('Email my alternate contacts a copy of this information') . '</label>
                				<div class="d-flex pl-4">
                                	<input title="Email my alternate contacts a copy of this information" type="radio" name="email_alt_contact" id="email_alt_contactyes" class="form-control normal" value="1" ' . (Session::get('user')->email_alt_contact == 1 ? 'checked="checked" ' : '') . ($this->form_mode == 'view' ? 'disabled="disabled"' : '') . '>
                                	<label for="email_alt_contactyes" class="form-radio mr-2 pl-1 pr-3">Yes</label>
                                	<input title="Do Not email my alternate contacts a copy of this information" type="radio" name="email_alt_contact" id="email_alt_contactno" class="form-control normal" value="0" ' . (Session::get('user')->email_alt_contact == 0 ? 'checked="checked" ' : '') . ($this->form_mode == 'view' ? 'disabled="disabled"' : '') . '>
                                	<label for="email_doctorno" class="form-radio mr-2 pl-1 pr-3">No</label>
                                </div>
                			</div>
                		</div>
                	</div>
				</div>';
        }

        $html .= $this->createHtml($fields, $orders, $request->user_id, $request->emergency);
        if (!Auth::check() || (Auth::check() && Session::get('user')->user_level_id == 2)) {
            // create section to list files uploaded by user and option to add more files
            $files = $this->fetchFiles($request->user_id, $category, $subcategory);
            $html .= '<div id="fileSection">';
            $html .= $this->createFileSection($files);
            $html .= '</div>';
        }
        if ($field_exists) {
            if ($this->data_exists && $this->form_mode == 'view') {
                Session::put('language_exists', Session::get('site_lang'));
                Session::put('cat_exists', $category);
                Session::put('subcat_exists', $subcategory);
            }

        }

        echo json_encode(['html' => $html, 'form_mode' => $this->form_mode, 'data_exists' => $this->data_exists, 'field_exists' => $field_exists, 'values' => $this->values, 'fields' => $this->fields]);
    }

    /**
     * create html from files user has selected during form submission
     */
    public function createFileSection($files)
    {
        $html = '<div class="row mt-3 mb-5 table-responsive">';
        if (count($files) > 0) {
            $html .= '<table class="table table-bordered"><tr><th width="25%">File Name</th><th>File Description</th><th width="10%" class="text-center">Action</th></tr>';

            //loop through array to create fields
            foreach ($files as $key => $value) {

                $html .= '<tr><td><a href="' . route('showFile', ['id' => $value->file_id, 'name' => explode('.', $value->file_name)[0]]) . '" class="editLinks showFile" target="_blank" title="a">' . $value->file_name . '</a></td><td>' . $value->file_description . '</td><td class="text-center"><a href="javascript:;" data-id="' . $value->file_id . '" class="removeFile"><i class="fa fa-times"></i></a></td></tr>';

            }
            $html .= '</table>';
        }

        $html .= '<a class="float-right editLinks mt-3 addFiles btn-submit text-center addFilesBtn" href="' . ((Auth::check()) ? '#addFileModal' : '#accessModal') . '" data-toggle="modal">Add Files</a>
                </div>';

        return $html;
    }

    /**
     * create html from fields and order array fetched from database
     */
    public function createHtml($fields, $orders, $user_id, $emergency)
    {
        $html = '';

        if ($emergency == 1) {
            //loop through array to create fields
            foreach ($orders as $key => $value) {
                //foreach ($ord as $key => $value) {
                if (!empty($key)) {
                    $html .= '<div class="row">';

                    if (is_array($value)) {
                        //loop through inner array if more than one element is present in a row
                        foreach ($value as $val) {
                            $html .= $this->createSection($fields[$val], count($value), $user_id);
                        }
                    } else {
                        $html .= $this->createSection($fields[$value], 1, $user_id);
                    }

                    $html .= '</div>';
                    //create blank row if display order is not present
                    if (!isset($orders[$key + 1])) {
                        $html .= '<div class="mb-2">
                            <div>&nbsp;</div>
                        </div>';
                    }
                }
                //}
            }
        } else {
            //loop through array to create fields
            foreach ($orders as $key => $value) {
                if (!empty($key)) {
                    $html .= '<div class="row">';

                    if (is_array($value)) {
                        //loop through inner array if more than one element is present in a row
                        foreach ($value as $val) {
                            $html .= $this->createSection($fields[$val], count($value), $user_id);
                        }
                    } else {
                        $html .= $this->createSection($fields[$value], 1, $user_id);
                    }

                    $html .= '</div>';
                    //create blank row if display order is not present
                    if (!isset($orders[$key + 1])) {
                        $html .= '<div class="mb-2">
                            <div>&nbsp;</div>
                        </div>';
                    }
                }

            }
        }
        return $html;
    }

    /**
     * create element according to the type of the tag coming from database
     */
    public function createElement($value, $count, $user_id)
    {
        $type = strtolower($value->tag_type);
        $format = strtolower($value->tag_format);
        $html = '';

        //calculate width of element according to the maxlength of the tag
        $width = ((250 / 20) * $value->tag_size);
        if ($value->tag_size < 10) {
            $width += 10;
        }

        // check if user has value for element
        if ($this->form_mode == 'view') {
            $field_val = (isset($this->values[$value->tag_id])) ? $this->values[$value->tag_id] : '';
            $orig_val = addslashes($field_val);
            $disabled = true;
        } else {
            $field_val = $value->tag_default_value;
            $orig_val = '';
            $disabled = false;
        }

        //switch statement to create elements according to the type
        switch ($type) {
            case 'integer':
            case 'int':
            case 'text':
            case 'date':
            case 'time':
            case 'timestamp':
            case 'datetime-local':
            case 'datetime':
                if ($type == 'integer' || $type == 'int') {
                    $type = 'text';
                }
                if ($type == 'date') {
                    $type = 'text';
                }
                if ($type == 'timestamp' || $type == 'datetime-local') {
                    $type = 'datetime-local';
                }

                if (empty($format)) {
                    //if tag size is greater than the configured character per row, then create textarea instead of textfield
                    if ($value->tag_size > Config::get('constants.CHARACTERS_PER_ROW')) {
                        $html .= '<div>
                                <textarea class="form-control normal textArea" cols="' . Config::get('constants.CHARACTERS_PER_ROW') . '" data-origval="' . $orig_val . '" rows="' . round($value->tag_size / Config::get('constants.CHARACTERS_PER_ROW')) . '" data-required="' . $value->tag_is_required . '" data-tablename="' . $value->tag_table_name . '" data-tagid="' . $value->tag_id . '"  data-dependent="' . $value->tag_is_dependent . '" data-dependent-field="' . $value->tag_dependency_field . '" wrap="hard" title="' . $value->tag_display_name . '" name="' . $value->tag_field_name . '" maxlength="' . $value->tag_size . '" style="height:' . (18.5 * round($value->tag_size / Config::get('constants.CHARACTERS_PER_ROW'))) . 'px"';
                        if ($disabled) {
                            $html .= ' disabled';
                        }

                        $html .= '>' . $field_val . '</textarea>
                            </div>';
                    } else {
                        $html = '<input class="form-control';
                        if (strtolower($value->tag_type) == 'integer' || strtolower($value->tag_type) == 'int') {
                            $html .= ' numberInput';
                        }
                        $html .= ' normal" data-required="' . $value->tag_is_required . '" data-tablename="' . $value->tag_table_name . '" data-origval="' . $orig_val . '" data-tagid="' . $value->tag_id . '" data-dependent="' . $value->tag_is_dependent . '" data-dependent-field="' . $value->tag_dependency_field . '" name="' . $value->tag_field_name . '" title="' . $value->tag_display_name . '" type="' . $type . '" maxLength="' . $value->tag_size . '"';
                        if (strtolower($value->tag_type) == 'integer' || strtolower($value->tag_type) == 'int') {
                            // $html .= ' onKeyPress="if(this.value.length>=' . $value->tag_size . ') return false;"';
                            // $html .= ' max="' . (str_repeat(9, $value->tag_size)) . '"';
                        }

                        $html .= ' value="' . $field_val . '" style="';

                        //set tag width if single field is there in a row
                        if ($count == 1) {
                            $html .= 'max-width:' . (($width <= 100 && $value->tag_size > 5) ? ($width + 100) : $width) . 'px;min-';
                        }

                        $html .= 'width:' . $width . 'px;"';
                        if ($disabled) {
                            $html .= ' disabled';
                        }

                        $html .= '>';
                    }

                } else {
                    switch ($format) {
                        case 'dropdown':
                            //fetch dropdown options from database by passing field name if it is independent field. If field is dependent on another field, then leave the options blank and fetch the options on change of another field
                            if ($value->tag_is_dependent == 0) {
                                $options = $this->fetchDropdown($value->tag_field_name);
                            } else {
                                $options = [];
                            }

                            $html = '<select title="' . $value->tag_display_name . '" class="form-control normal" data-origval="' . $orig_val . '" data-required="' . $value->tag_is_required . '" data-tablename="' . $value->tag_table_name . '" data-tagid="' . $value->tag_id . '" data-dependent="' . $value->tag_is_dependent . '" data-dependent-field="' . $value->tag_dependency_field . '" name="' . $value->tag_field_name . '" style="max-width:' . $width . 'px;width:50%"';
                            if ($disabled) {
                                $html .= ' disabled';
                            }

                            $html .= '>';

                            foreach ($options as $option) {
                                $html .= '<option value="' . $option->value_name . '"';
                                if ($field_val == $option->value_name) {
                                    $html .= ' selected="selected"';
                                }

                                $html .= '>' . $option->value_name . '</option>';
                            }

                            $html .= '</select>';
                            break;

                        case 'list':
                            // fetch options for list from their respective tables
                            $options = $this->fetchList($value->tag_field_name, $user_id);
                            $html = '<select title="' . $value->tag_display_name . '" class="form-control normal" data-origval="' . $orig_val . '" data-required="' . $value->tag_is_required . '" data-tablename="' . $value->tag_table_name . '" data-tagid="' . $value->tag_id . '" data-dependent="' . $value->tag_is_dependent . '" data-dependent-field="' . $value->tag_dependency_field . '" name="' . $value->tag_field_name . '" multiple style="max-width:' . $width . 'px;width:50%"';
                            if ($disabled) {
                                $html .= ' disabled';
                            }

                            $html .= '>';
                            $vals = explode(',', $field_val);
                            foreach ($options as $option) {
                                $html .= '<option value="' . $option->value_name . '"';
                                if (in_array($option->value_name, $vals)) {
                                    $html .= ' selected="selected"';
                                }

                                $html .= '>' . $option->value_name . '</option>';
                            }

                            $html .= '</select>';
                            break;

                    }
                }
                break;

            case 'boolean':
                // if (!empty($format)) {
                //     switch ($format) {
                //     case 'checkbox':
                if (stripos(\Illuminate\Support\Facades\Request::segment(1), 'ice') !== false) {
                    $html = '<label class="mr-2 mb-0">&nbsp;' . ($orig_val == 1 ? 'Yes' : 'No') . '</label>';
                } else {
                    $html = '<div class="d-flex">
                                <input title="Yes" type="radio" data-tagid="' . $value->tag_id . '" class="form-control normal" data-required="' . $value->tag_is_required . '" data-origval="' . $orig_val . '" name="' . $value->tag_field_name . '" data-tablename="' . $value->tag_table_name . '" data-dependent="' . $value->tag_is_dependent . '" data-dependent-field="' . $value->tag_dependency_field . '" value="1" id="' . $value->tag_field_name . '1"';
                    if ($field_val == "1") {
                        $html .= 'checked="checked"';
                    }

                    if ($disabled) {
                        $html .= ' disabled';
                    }

                    $html .= '><label class="form-radio mr-2" id="' . $value->tag_field_name . '1">&nbsp;Yes</label>
                                <input type="radio" title="No" class="form-control normal" data-origval="' . $orig_val . '" data-required="' . $value->tag_is_required . '" data-tagid="' . $value->tag_id . '" name="' . $value->tag_field_name . '" data-tablename="' . $value->tag_table_name . '" data-dependent="' . $value->tag_is_dependent . '" data-dependent-field="' . $value->tag_dependency_field . '" value="0" id="' . $value->tag_field_name . '0" ';
                    if (strlen($field_val) != 0 && $field_val == "0") {
                        $html .= 'checked="checked"';
                    }

                    if ($disabled) {
                        $html .= ' disabled';
                    }

                    $html .= '><label class="form-radio mr-2" id="' . $value->tag_field_name . '0">&nbsp;No</label>
                            </div>';
                }
                //         break;
                //     }
                // }
                break;

            case "blob":
                $html .= '<div>
                        <textarea title="' . $value->tag_display_name . '" class="form-control normal textArea" data-origval="' . $orig_val . '" data-tablename="' . $value->tag_table_name . '" data-tagid="' . $value->tag_id . '" data-required="' . $value->tag_is_required . '" data-dependent="' . $value->tag_is_dependent . '" data-dependent-field="' . $value->tag_dependency_field . '" cols="' . Config::get('constants.CHARACTERS_PER_ROW') . '" rows="' . round($value->tag_size / Config::get('constants.CHARACTERS_PER_ROW')) . '" wrap="hard" name="' . $value->tag_field_name . '" maxlength="' . $value->tag_size . '" style="height:' . (18.5 * round($value->tag_size / Config::get('constants.CHARACTERS_PER_ROW'))) . 'px"';
                if ($disabled) {
                    $html .= ' disabled';
                }

                $html .= '>' . $field_val . '</textarea>
                    </div>';
                break;

            case "table":
                $columns = DB::select('CALL sp_get_table_columns(?)', [$value->tag_display_name]);

                $html .= '<table class="table table-bordered displayTable" data-tablename="' . $value->tag_table_name . '"><tbody><tr class="headings">';

                foreach ($columns as $k => $v) {
                    $html .= '<th>' . $v->tag_column_name . '</th>';
                }
                $html .= '<th><a href="javascript:;" class="btn btn-submit addNewTable w-100" data-displayname="' . $value->tag_display_name . '" data-tablename="' . $value->tag_table_name . '" style="display:none">Add New</a></th>';

                $html .= '</tr>';

                $data = DB::select('CALL sp_get_user_tabledata(?,?,?)', [$user_id, Session::get('site_lang'), strtolower($value->tag_table_name) . '_display_table']);

                foreach ($data as $kd => $d) {
                    $html .= '<tr>';
                    foreach ($columns as $kc => $c) {

                        $html .= '<td>' . $d->{$this->createColumnFieldName($c->tag_column_name)} . '</td>';
                    }
                    $html .= '<td>&nbsp;</td></tr>';
                }

                $html .= '</tbody></table>';

                break;
        }
        return $html;
    }

    /**
     * create section according to value coming from database
     */
    public function createSection($val, $count, $user_id)
    {

        if ($val->tag_size > Config::get('constants.CHARACTERS_PER_ROW')) {
            //html for textarea
            $html = '<div class="form-group col-lg-12"><div class="mb-2 px-3"';
            if ($val->tag_is_hidden == 1) {
                $html .= ' style="display:none"';
            }
            $html .= '>';
            $html .= "<div class='text-nowrap mb-1'>" . mb_strtoupper($val->tag_display_name);
        } else {
            //html for rest of the tags
            $html = '<div class="mb-2 pr-2 pl-3 ';
            if ($count > 1) {
                // $html .= 'flex-fill';
            } else {
                $html .= 'col-md-6';
            }
            if (strtolower($val->tag_type) == 'date') {
                $html .= ' formdate';
            }
            if ($val->tag_is_hidden == 1) {
                $html .= '" style="display:none;';
            }

            $html .= '">';
            $html .= '    <div class="';

            if (strtolower($val->tag_type) != 'table') {
                $html .= 'form-group';
            }

            $html .= '"><div class="';

            if (strtolower($val->tag_type) != 'table') {
                $html .= 'd-flex flex-column flex-lg-row';
            }
            $html .= '">
                <div class=" mr-3';

            if (strtolower($val->tag_type) != 'table') {
                $html .= ' d-flex';
            }
            $html .= ' align-items-center">';

            if (strtolower($val->tag_type) == 'instr') {
                $html .= '<strong>';
            }

            if (strtolower($val->tag_format) == 'header2') {
                $html .= '<h2>';
            }

            $html .= mb_strtoupper($val->tag_display_name);

            if (strtolower($val->tag_type) == 'instr') {
                $html .= '</strong>';
            }

            if (strtolower($val->tag_format) == 'header2') {
                $html .= '</h2>';
            }
        }
        if ($val->tag_is_required == 1) {
            $html .= '<span class="textred">*</span>';
        }

        if (!empty($val->tag_display_hint)) {
            $html .= '<img src="' . Helper::getProjectName() . '/public/css/info2.svg" class="tooltips" data-toggle="tooltip" data-placement="bottom" alt="' . $val->tag_display_hint . '" title="' . $val->tag_display_hint . '">';
        }
        $html .= '</div>';

        if (!in_array(strtolower($val->tag_type), ['instr']) && (!in_array(strtolower($val->tag_format), ['header2']) || strtolower($val->tag_type) == 'table')) {
            //create element from the data
            $html .= $this->createElement($val, $count, $user_id);
        }

        /*if (!empty($val->tag_description) && $count == 1) {
        $html .= '<small style="padding:5px">' . $val->tag_description . '</small>';
        }*/
        $html .= '    </div>';

        /*if (!empty($val->tag_description) && $count > 1) {
        $html .= '<small>' . $val->tag_description . '</small>';
        }*/
        $html .= '</div></div>';
        return $html;
    }

    /**
     * fetch dropdown options from database
     */
    public function fetchDropdown($fieldname)
    {
        $options = DB::select('CALL sp_get_all_drop_down_by_field_name(?,?)', [$fieldname, null]);
        return $options;
    }

    /**
     * fetch list options from database
     */
    public function fetchList($fieldname, $user_id)
    {
        $options = DB::select('CALL sp_get_user_list_options(?,?)', [$fieldname, $user_id]);
        return $options;
    }

    public function showRecords($cat, $subcat, $filename)
    {
        if (isset(Session::get('user')->user_id)) {
            Session::put('cat_exists', $cat);
            if ($subcat != '1') {
                Session::put('subcat_exists', $subcat);
            }

            Session::put('filename', $filename);

            return redirect()->route('records');
        } else {
            return redirect('/');
        }
    }

    /**
     *    function to fetch values for user_id from tables (if exists)
     */
    public function fetchFormValues($user_id)
    {
        $tag_ids = implode(',', $this->tag_ids);
        // $tag_ids = 1;

        // fetch values from database for a user of specific category or all categories depending on the data mode
        $data = DB::select('CALL sp_read_customer_data(?,?,?,?)', [$this->data_mode, $user_id, $tag_ids, Session::get('site_lang')]);

        // if data exists, then update the form_mode to view otherwise in add mode
        $this->form_mode = (count($data) > 0 && isset($data[0]->tag_id)) ? 'view' : 'add';
        $this->data_exists = (count($data) > 0 && isset($data[0]->tag_id)) ? true : false;

        return $data;

    }

    /**
     *    function to store files and upload on server
     */
    public function uploadFile(Request $request)
    {

        $validate = Validator($request->all(), [
            'date' => 'required|date',
            'description' => 'required',
            'file' => 'required',
        ]);

        if ($validate->fails()) {
            echo json_encode(['status' => 0, 'messages' => $validate->errors()]);
        } else {

            // check file exist or not
            if ($request->hasfile('file')) {
                // fetch all details from file data and upload to server
                $file = $request->file('file');
                $size = $file->getSize();
                $filename = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();
                $fileguid = time() . '.' . $extension;
                $file->move("public/uploads/documents/$request->user_id/", $fileguid);

                $subcategory = empty($request->subcategory) ? '' : $request->subcategory;

                $save = DB::update('CALL sp_save_user_datafiles(?,?,?,?,?,?,?,?,?)', [$request->user_id, $request->category, $subcategory, date('Y-m-d', strtotime($request->date)), $request->description, $filename, $fileguid, $size, Session::get('site_lang')]);

                if ($save > 0) {

                    // create section to list files uploaded by user and option to add more files
                    $files = $this->fetchFiles($request->user_id, $request->category, $subcategory);
                    $html = $this->createFileSection($files);
                    echo json_encode(['status' => 1, 'messages' => 'File uploaded successfully.', 'html' => $html]);
                } else {

                    echo json_encode(['status' => 0, 'messages' => 'Unable to save file. Please try again later']);
                }
            }
        }

    }

    /**
     *    function to store values in database
     */
    public function storeValue(Request $request)
    {

        $value = explode('>>', $request->data);
        $fields = $request->fields;
        // dd($request->all());
        $rules = [];
        $attr = [];
        $tagids = [];
        $values = [];
        // send only those values which are new or changed
        foreach ($value as $k => $v) {
            $detail = explode('=', $v);
            if (count($detail) > 1) {
                $field = $fields[$detail[0]];
                // echo $detail[0];
                // print_r($field);
                $name = $field['tag_field_name'];
                $request[$name] = $detail[1];
                if (!in_array($field['tag_id'], $tagids)) {
                    // prepare data to send to procedure call
                    array_push($tagids, $field['tag_id']);
                    array_push($values, $detail[1]);
                }
            }
        }
        // die;
        // add rules to field depending on its type
        foreach ($fields as $key => $field) {
            if (isset($request[$field['tag_field_name']])) {
                $rules[$field['tag_field_name']] = '';
                $attr[$field['tag_field_name']] = $field['tag_display_name'];
                if ($field['tag_is_required'] == 1) {
                    $rules[$field['tag_field_name']] .= 'required|';
                }
                if (strtolower($field['tag_type']) == 'integer' || strtolower($field['tag_type']) == 'int') {
                    $rules[$field['tag_field_name']] .= 'integer|';
                }
                if (strtolower($field['tag_type']) == 'date') {
                    $rules[$field['tag_field_name']] .= 'date|';
                }
                if (stripos($field['tag_field_name'], 'name') !== false) {
                    // $rules[$field['tag_field_name']] .= 'alpha|';
                }
                if (stripos($field['tag_field_name'], 'middle') !== false) {
                    // $rules[$field['tag_field_name']] .= 'alpha|';
                }
                $rules[$field['tag_field_name']] = rtrim($rules[$field['tag_field_name']], '|');
            }
        }

        // validate values according to the rules fetched and send the error messages back to the ajax request to display on the screen
        $validate = Validator($request->all(), $rules);
        $validate->setAttributeNames($attr);
        if ($validate->fails()) {
            echo json_encode(['status' => 0, 'messages' => $validate->errors(), 'attr' => $attr]);
        } else {
            $tagids = implode(',', $tagids);
            $values = implode('>>', $values);
            $email_alt_contact = isset($request->email_alt_contact) ? $request->email_alt_contact : Session::get('user')->email_alt_contact;
            $timestamp = date('Y-m-d H:i:s');
            if ($email_alt_contact == Session::get('user')->email_alt_contact) {
                $timestamp = Session::get('user')->last_update_email_alt_contact;
            }
            // echo $tagids . "<br>" . $values;die();
            $result = DB::update('CALL sp_insert_update_customer_data(?,?,?,?,?,?,?)', [$this->data_mode, $tagids, $values, $request->user_id, Session::get('site_lang'), $email_alt_contact, $timestamp]);

            // check if user has not selected all the 5 alternate languages and the currecnt language is not within those selected languages
            $user = Session::get('user');
            if ($user) {
                $id = $user->user_id;

                $lang1 = $user->alternate_language_id1;
                $lang2 = $user->alternate_language_id2;
                $lang3 = $user->alternate_language_id3;
                $lang4 = $user->alternate_language_id4;
                $lang5 = $user->alternate_language_id5;

                if ($lang1 != Session::get('site_lang') && $lang2 != Session::get('site_lang') && $lang3 != Session::get('site_lang') && $lang4 != Session::get('site_lang') && $lang5 != Session::get('site_lang')) {
                    if ($user->alternate_language_id1 == 0) {
                        $lang1 = Session::get('site_lang');
                        $lang2 = $user->alternate_language_id2;
                        $lang3 = $user->alternate_language_id3;
                        $lang4 = $user->alternate_language_id4;
                        $lang5 = $user->alternate_language_id5;
                    } elseif ($user->alternate_language_id2 == 0) {
                        $lang2 = Session::get('site_lang');
                        $lang1 = $user->alternate_language_id1;
                        $lang3 = $user->alternate_language_id3;
                        $lang4 = $user->alternate_language_id4;
                        $lang5 = $user->alternate_language_id5;
                    } elseif ($user->alternate_language_id3 == 0) {
                        $lang3 = Session::get('site_lang');
                        $lang2 = $user->alternate_language_id2;
                        $lang1 = $user->alternate_language_id1;
                        $lang4 = $user->alternate_language_id4;
                        $lang5 = $user->alternate_language_id5;
                    } elseif ($user->alternate_language_id4 == 0) {
                        $lang4 = Session::get('site_lang');
                        $lang2 = $user->alternate_language_id2;
                        $lang3 = $user->alternate_language_id3;
                        $lang1 = $user->alternate_language_id1;
                        $lang5 = $user->alternate_language_id5;
                    } elseif ($user->alternate_language_id5 == 0) {
                        $lang5 = Session::get('site_lang');
                        $lang2 = $user->alternate_language_id2;
                        $lang3 = $user->alternate_language_id3;
                        $lang4 = $user->alternate_language_id4;
                        $lang1 = $user->alternate_language_id1;
                    }

                    $name = DB::update('CALL sp_update_user_alternate(?,?,?,?,?,?)', [$id, $lang1, $lang2, $lang3, $lang4, $lang5]);
                }
            }

            if ($result) {
                echo json_encode(['status' => 1, 'messages' => 'Data successfully submitted']);
            }

        }
    }

    /**
     *    function to store table values in database
     */
    public function storeTableData(Request $request)
    {

        $value = explode('>>', $request->data);
        $table = strtolower($request->table) . '_display_table';
        $rules = [];
        $attr = [];
        $names = [];
        $values = [];
        // send only those values which are new or changed
        foreach ($value as $k => $v) {
            $detail = explode('=', $v);
            if (count($detail) > 1) {
                $request[$detail[0]] = $detail[1];
                if (!in_array($detail[0], $names)) {
                    // prepare data to send to procedure call
                    array_push($names, $detail[0]);
                    array_push($values, '"' . $detail[1] . '"');
                }
            }
        }
        $names = implode(',', $names);
        $values = implode(',', $values);

        // echo $names . "<br>" . $values;die();
        $result = DB::update('CALL sp_insert_update_customer_tabledata(?,?,?,?,?,?)', [$this->data_mode, $names, $values, $request->user_id, Session::get('site_lang'), $table]);

        if ($result) {
            echo json_encode(['status' => 1, 'messages' => 'Data successfully submitted']);
        }
    }

    /**
     * fetch dropdown options from database
     */
    public function fetchDropdownValues(Request $request)
    {
        $options = DB::select('CALL sp_get_all_drop_down_by_field_name(?,?)', [$request->fieldname, $request->val]);
        echo json_encode($options);
    }

    /**
     * display page for ice data
     */
    public function ice()
    {
        Session::forget('site_lang');
        return view('ice');
    }

    /**
     * display ice form
     */
    public function iceData($id)
    {

        $user_id = base64_decode($id);

        $user = DB::select('CALL sp_get_user_details(?)', [$user_id]);
        $ids = DB::select('CALL sp_get_user_data_languages(?)', [$user_id]);

        if (!Session::has('site_lang')) {
            Session::put('site_lang', $user[0]->default_language);
        }
        $language = DB::select('CALL sp_get_all_languages(?,?)', [Session::get('site_lang'), "(" . $ids[0]->ids . ")"]);

        return view('ice_form', compact('user_id', 'language'));
    }

    /**
     * check card id in user_accounts table
     *
     * @return \Illuminate\Http\Response
     */
    public function checkUserCard(Request $request)
    {
        // get user information from the card id and check whether it's active or not
        $user = DB::select('CALL sp_check_user_card(?)', [$request->card]);
        if (isset($user[0]->user_id)) {
            if ($user[0]->is_active == 1) {
                if ($user[0]->user_level_id == 1) {
                    echo json_encode(['status' => 0, 'message' => 'As a Free user, this option is not available. To Upgrade to Full Access go to <a href="https://www.mylifeid.com">https://www.mylifeid.com</a> and purchase your MyLifeID Pocket Cloud, email <a href="mailto:Sales@MyLifeID.com">Sales@MyLifeID.com</a> or call sales at <a href="tel:702-832-0112">702-832-0112</a>.']);
                } else {
                    $code = base64_encode($user[0]->user_id);
                    echo json_encode(['status' => 1, 'message' => "Valid Card ID", 'id' => $user[0]->user_id, 'code' => urlencode($code)]);
                }
            } else {
                echo json_encode(['status' => 0, 'message' => "The MyLifeID Pocket Cloud card id you entered is not an active card. Please enter the correct id or go to <a href='https://users.mylifeid.com' target='_blank'>https://users.mylifeid.com</a> to register your device. If you need assistance, call 702-832-0112."]);
            }
        } else {
            echo json_encode(['status' => 0, 'message' => "The MyLifeID Pocket Cloud card id you entered is not an active card. Please enter the correct id or go to <a href='https://users.mylifeid.com' target='_blank'>https://users.mylifeid.com</a> to register your device. If you need assistance, call 702-832-0112."]);
        }
    }

    /**
     * function to fetch emergency
     */

    public function fetchIceData(Request $request)
    {
        // print_r(Session::get('site_lang'));die();
        $data = DB::select('CALL sp_get_ice_data_tag_definitions(?)', [Session::get('site_lang')]);

        $fields = [];
        $orders = [];

        //create order array with orders as key and tag id as value.
        //create another array with tag id as key and complete object as its value.
        foreach ($data as $key => $value) {
            // check site language and if not english, replace the form field and form field values with the updated value respect to the language
            if (Session::get('site_lang') != 26 && isset($value->tag_display_translation_name) && $value->tag_display_translation_name != null) {
                $value->tag_display_name = $value->tag_display_translation_name;
            }
            if (Session::get('site_lang') != 26 && isset($value->tag_default_translation_value) && $value->tag_default_translation_value != null) {
                $value->tag_default_value = $value->tag_default_translation_value;
            }
            if (Session::get('site_lang') != 26 && isset($value->tag_translation_description) && $value->tag_translation_description != null) {
                $value->tag_description = $value->tag_translation_description;
            }
            if (Session::get('site_lang') != 26 && isset($value->tag_display_translation_hint) && $value->tag_display_translation_hint != null) {
                $value->tag_display_hint = $value->tag_display_translation_hint;
            }
            if (Session::get('site_lang') != 26 && isset($value->tag_public_translation_name) && $value->tag_public_translation_name != null) {
                $value->tag_public_note = $value->tag_public_translation_name;
            }
            if (Session::get('site_lang') != 26 && isset($value->tag_private_translation_name) && $value->tag_private_translation_name != null) {
                $value->tag_private_note = $value->tag_private_translation_name;
            }

            array_push($this->tag_ids, $value->tag_id);
            //split order with letters and alphabets.
            $p = preg_split('/(?<=[0-9])(?=[a-zA-Z]+)/i', $value->tag_ice_display_order);
            if (count($p) > 1) {
                //if order contains alphabets, make another array for that row.
                if (!isset($orders[$p[0]])) {
                    $orders[$p[0]] = array();
                }
                $t = strtoupper($p[1]);

                // in case of same display order of multiple category fields (emergency data), append number with alphabet until found unique
                if (isset($orders[$p[0]][$t])) {
                    $temp = $t;
                    $i = 1;
                    do {
                        $temp = $temp . ($i++);
                    } while (isset($orders[$p[0]][$temp]));
                    $t = $temp;
                }

                $orders[$p[0]][$t] = $value->tag_id;
                //sort internal array with alphabets
                uksort(
                    $orders[$p[0]],
                    function ($a, $b) {
                        sscanf($a, '%[A-Z]%d', $ac, $ar);
                        sscanf($b, '%[A-Z]%d', $bc, $br);
                        return ($ac == '' || $bc == '') ? ($a >= $b) : (($ar == $br) ? $ac <=> $bc : $ar <=> $br);
                    }
                );
            } else {
                if (!isset($orders[$p[0]])) {
                    $orders[$p[0]] = array();
                }
                array_push($orders[$p[0]], $value->tag_id);

                uksort(
                    $orders[$p[0]],
                    function ($a, $b) {
                        sscanf($a, '%[A-Z]%d', $ac, $ar);
                        sscanf($b, '%[A-Z]%d', $bc, $br);
                        return ($ac == '' || $bc == '') ? ($a >= $b) : (($ar == $br) ? $ac <=> $bc : $ar <=> $br);
                    }
                );
            }
            $fields[$value->tag_id] = $value;
        }
        //sort array by keys(display order)
        //ksort($orders);
        // print_r($orders);die();
        //move element with 0 display order from top to bottom
        if (isset($orders[0])) {
            $zeroOrder = $orders[0];
            unset($orders[0]);
            $orders[0] = $zeroOrder;
        }

        $this->fields = $fields;

        if (count($data) > 0) {
            // fetch tag values from database
            $field_values = $this->fetchFormValues($request->user_id);

            // customize value array with tag_id as key and value as value
            foreach ($field_values as $key => $value) {
                if (isset($value->tag_id)) {
                    $this->values[$value->tag_id] = $value->value;
                }

            }
        }

        $field_exists = (count($data) > 0);

        $html = $this->createHtml($fields, $orders, $request->user_id, 1);

        if (!Auth::check() || (Auth::check() && Session::get('user')->user_level_id == 2)) {
            // create section to list files uploaded by user and option to add more files
            $files = $this->fetchFiles($request->user_id, 'ICE', '');
            $html .= '<div id="fileSection">';
            $html .= $this->createFileSection($files);
            $html .= '</div>';
        }
        echo json_encode(['html' => $html, 'form_mode' => $this->form_mode, 'data_exists' => $this->data_exists, 'field_exists' => $field_exists, 'values' => $this->values, 'fields' => $this->fields]);
    }
    /**
     *    function to fetch files for user_id from table (if exists)
     */
    public function fetchFiles($user_id, $category, $subcategory)
    {

        // fetch files from database for a user of specific category
        $data = DB::select('CALL sp_get_user_datafiles(?,?,?,?)', [$user_id, $category, $subcategory, Session::get('site_lang')]);

        return $data;

    }

    /**
     *    function to delete file for user_id from table (if exists)
     */
    public function deleteFile(Request $request)
    {

        // delete file from database
        $data = DB::select('CALL sp_delete_user_data_file(?)', [$request->id]);

        if (isset($data[0]->file_id)) {

            // create section to list files uploaded by user and option to add more files
            $files = $this->fetchFiles($data[0]->user_id, $data[0]->category, $data[0]->subcategory);
            $html = $this->createFileSection($files);
            echo json_encode(['status' => 1, 'messages' => 'File deleted successfully.', 'html' => $html]);
        } else {

            echo json_encode(['status' => 0, 'messages' => 'Unable to delete file. Please try again later']);
        }

    }

    /**
     *    function to show file when user clicks on the file name
     */
    public function showFile($id, $name)
    {

        $data = DB::select('CALL sp_get_userdata_by_fileid(?)', [$id]);
        $file = base_path() . '/public/uploads/documents/' . $data[0]->user_id . '/' . $data[0]->file_guid;

        return response()->file($file, [
            'Content-Disposition' => 'inline; filename="' . $data[0]->file_name . '"',
        ]);
        // return view('users.viewfile', compact('data', 'content_types'));

    }

    /**
     * fetch company names from database for selected source type
     */
    public function fetchSourceName(Request $request)
    {
        $options = DB::select('CALL sp_fetch_data_source_name(?)', [$request->value]);
        echo json_encode($options);
    }

    /**
     *    function to store files from another source and upload on server
     */
    public function uploadSourceFile(Request $request)
    {

        $user = Session::get('user');
        $page_title = 'Upload medical record';
        $show_upload_btn = false;

        $sources = DB::select('CALL sp_get_data_sources');
        if ($request->method() == 'POST') {
            $validate = Validator($request->all(), [
                'file_date' => 'required|date',
                'file_description' => 'required',
                'file_name' => 'required',
                'upload_source_id' => 'required',
                'company_name' => 'required',
                'company_phone' => 'required',
                'agree_terms' => 'required',
            ]);

            if ($validate->fails()) {
                return redirect()->back()->withInput($request->all())->withErrors($validate);
            } else {

                // check file exist or not
                if ($request->hasfile('file_name')) {
                    // fetch all details from file data and upload to server
                    $file = $request->file('file_name');
                    $size = $file->getSize();
                    $filename = $file->getClientOriginalName();
                    $extension = $file->getClientOriginalExtension();
                    $fileguid = time() . '.' . $extension;
                    $file->move("public/uploads/source documents/" . Session::get('user')->user_id . "/", $fileguid);

                    $agree_terms = isset($request->agree_terms) && $request->agree_terms == 'on' ? 1 : 0;

                    $save = DB::update('CALL sp_save_user_datasourcefiles(?,?,?,?,?,?,?,?,?,?,?)', [Session::get('user')->user_id, $request->upload_source_id, $request->company_name, $request->company_phone, $agree_terms, date('Y-m-d', strtotime($request->file_date)), $request->file_description, $filename, $fileguid, $size, Session::get('site_lang')]);

                    if ($save > 0) {

                        $data = array(
                            'name' => Session::get('user')->salutation . " " . Session::get('user')->first_name,
                            'guid' => $fileguid,
                        );

                        $sender = 'support@mylifeid.com';
                        $mail = Mail::send('email.source_file_upload', $data, function ($message) use ($data, $sender) {
                            $message->to('support@mylifeid.com', 'MyLifeID Support')->subject('New File Uploaded From Another Source');
                            $message->from($sender, 'MyLifeID Support');
                        });

                        // create section to list files uploaded by user and option to add more files
                        $files = $this->fetchSourceFiles($request->user_id);
                        $request->session()->flash('success', 'File uploaded successfully.');
                        return redirect()->route('uploads');
                    } else {

                        $request->session()->flash('error', 'Unable to save file. Please try again later');
                        return redirect()->back();
                    }
                }
            }
        }

        return view('users.upload_form', compact('sources', 'user', 'page_title', 'show_upload_btn'));

    }

    /**
     *    function to fetch files for user_id from table (if exists) that he uploaded from another source
     */
    public function fetchSourceFiles($user_id)
    {

        // fetch files uploaded from another source from database for a user
        $data = DB::select('CALL sp_get_user_datasourcefiles(?,?)', [$user_id, Session::get('site_lang')]);
        return $data;

    }

    /**
     *    function to delete file for user_id from table (if exists) that he uploaded from another source
     */
    public function deleteSourceFile(Request $request)
    {

        // delete file from database
        $data = DB::select('CALL sp_delete_user_data_source_file(?)', [$request->id]);

        if ($data) {

            // create section to list files uploaded by user and option to add more files
            $files = $this->fetchSourceFiles(Session::get('user')->user_id);
            echo json_encode(['status' => 1, 'messages' => 'File deleted successfully.', 'id' => $request->id]);
        } else {

            echo json_encode(['status' => 0, 'messages' => 'Unable to delete file. Please try again later']);
        }

    }

    /**
     *    function to show file when user clicks on the file name that he uploaded from another source
     */
    public function showSourceFile($id, $name)
    {

        $data = DB::select('CALL sp_get_usersourcedata_by_fileid(?)', [$id]);
        $file = base_path() . '/public/uploads/source documents/' . $data[0]->user_id . '/' . $data[0]->file_guid;

        return response()->file($file, [
            'Content-Disposition' => 'inline; filename="' . $data[0]->file_name . '"',
        ]);
        // return view('users.viewfile', compact('data', 'content_types'));

    }

    /**
     * function called from ajax to create row with form for tag type table
     */
    public function createTableColumn(Request $request)
    {
        $displayname = $request->displayname;
        $tablename = $request->tablename;

        $columns = DB::select('CALL sp_get_table_columns(?)', [$displayname]);

        $html = '<tr>';

        foreach ($columns as $key => $value) {
            $html .= '<td class="' . (in_array($value->tag_column_type, ['date']) ? 'formdate' : '') . '"><div class="form-group">' . $this->createColumnElement($value, 1, Session::get('user')->user_id, $tablename) . '</div></td>';
        }

        $html .= '<td><a href="javascript:;" title="Save" data-toggle="tooltip" class="saveBtn"><i class="fa fa-floppy-o" aria-hidden="true"></i></a> &nbsp;&nbsp;&nbsp; <a href="javascript:;" title="Cancel" class="cancelBtn" data-toggle="tooltip"><i class="fa fa-times" aria-hidden="true"></i></a></td></tr>';
        return $html;
    }

    public function createColumnFieldName($display)
    {
        return str_replace(' ', '_', preg_replace('/[^A-Za-z0-9\-]/', '', $display));
    }

    /**
     * create element according to the type of the tag coming from database for table columns
     */
    public function createColumnElement($value, $count, $user_id, $table)
    {
        $type = strtolower($value->tag_column_type);
        $format = strtolower($value->tag_column_format);
        $html = '';

        //switch statement to create elements according to the type
        switch ($type) {
            case 'integer':
            case 'int':
            case 'text':
            case 'time':
            case 'timestamp':
            case 'datetime':
            case 'datetime-local':
            case 'date':
                if ($type == 'integer' || $type == 'int') {
                    $type = 'text';
                }
                if ($type == 'date') {
                    $type = 'text';
                }
                if ($type == 'timestamp' || $type == 'datetime') {
                    $type = 'datetime-local';
                }

                if (empty($format)) {
                    //if tag size is greater than the configured character per row, then create textarea instead of textfield
                    if ($value->tag_column_size > Config::get('constants.CHARACTERS_PER_ROW')) {
                        $html .= '<div>
                                <textarea class="form-control normal textArea" cols="' . Config::get('constants.CHARACTERS_PER_ROW') . '" data-required="' . $value->tag_column_required . '" data-tablename="' . $table . '" data-tagid="' . $value->tag_object_id . '" wrap="hard" title="' . $value->tag_column_name . '" name="' . $this->createColumnFieldName($value->tag_column_name) . '" maxlength="' . $value->tag_column_size . '" data-table="yes"></textarea>
                            </div>';
                    } else {
                        $html = '<input class="form-control';
                        if (strtolower($value->tag_column_type) == 'integer' || strtolower($value->tag_column_type) == 'int') {
                            $html .= ' numberInput';
                        }
                        $html .= ' normal" data-table="yes" data-required="' . $value->tag_column_required . '" data-tablename="' . $table . '" data-tagid="' . $value->tag_object_id . '" name="' . $this->createColumnFieldName($value->tag_column_name) . '" title="' . $value->tag_column_name . '" type="' . $type . '" maxLength="' . $value->tag_column_size . '"';
                        if (strtolower($value->tag_column_type) == 'integer' || strtolower($value->tag_column_type) == 'int') {
                            // $html .= ' onKeyPress="if(this.value.length>=' . $value->tag_size . ') return false;"';
                            // $html .= ' max="' . (str_repeat(9, $value->tag_size)) . '"';
                        }

                        $html .= '>';
                    }

                } else {
                    switch ($format) {
                        case 'dropdown':
                            //fetch dropdown options from database by passing field name if it is independent field. If field is dependent on another field, then leave the options blank and fetch the options on change of another field

                            $options = $this->fetchDropdown($this->createColumnFieldName($value->tag_column_name));

                            $html = '<select data-table="yes" title="' . $value->tag_column_name . '" class="form-control normal" data-required="' . $value->tag_column_required . '" data-tablename="' . $table . '" data-tagid="' . $value->tag_object_id . '" name="' . $this->createColumnFieldName($value->tag_column_name) . '">';

                            foreach ($options as $option) {
                                $html .= '<option value="' . $option->value_name . '"';
                                if ($field_val == $option->value_name) {
                                    $html .= ' selected="selected"';
                                }

                                $html .= '>' . $option->value_name . '</option>';
                            }

                            $html .= '</select>';
                            break;

                        case 'list':
                            // fetch options for list from their respective tables
                            $options = $this->fetchList($this->createColumnFieldName($value->tag_column_name), $user_id);
                            $html = '<select data-table="yes" title="' . $value->tag_column_name . '" class="form-control normal" data-required="' . $value->tag_column_required . '" data-tablename="' . $table . '" data-tagid="' . $value->tag_object_id . '" name="' . $this->createColumnFieldName($value->tag_column_name) . '" multiple>';
                            $vals = explode(',', $field_val);
                            foreach ($options as $option) {
                                $html .= '<option value="' . $option->value_name . '"';
                                if (in_array($option->value_name, $vals)) {
                                    $html .= ' selected="selected"';
                                }

                                $html .= '>' . $option->value_name . '</option>';
                            }

                            $html .= '</select>';
                            break;

                    }
                }
                break;

            case 'boolean':
                // if (!empty($format)) {
                //     switch ($format) {
                //     case 'checkbox':
                if (stripos(\Illuminate\Support\Facades\Request::segment(1), 'ice') !== false) {
                    $html = '<label class="mr-2 mb-0">&nbsp;' . ($orig_val == 1 ? 'Yes' : 'No') . '</label>';
                } else {
                    $html = '<div class="d-flex">
                                <input data-table="yes" title="Yes" type="radio" data-tagid="' . $value->tag_object_id . '" class="form-control normal" data-required="' . $value->tag_column_required . '" name="' . $this->createColumnFieldName($value->tag_column_name) . '" data-tablename="' . $table . '" value="1" id="' . $this->createColumnFieldName($value->tag_column_name) . '1"><label class="form-radio mr-2" id="' . $this->createColumnFieldName($value->tag_column_name) . '1">&nbsp;Yes</label>
                                <input data-table="yes" type="radio" title="No" class="form-control normal"  data-required="' . $value->tag_column_required . '" data-tagid="' . $value->tag_object_id . '" name="' . $this->createColumnFieldName($value->tag_column_name) . '" data-tablename="' . $table . '" value="0" id="' . $this->createColumnFieldName($value->tag_column_name) . '0" ><label class="form-radio mr-2" id="' . $this->createColumnFieldName($value->tag_column_name) . '0">&nbsp;No</label>
                            </div>';
                }
                //         break;
                //     }
                // }
                break;

            case "blob":
                $html .= '<div>
                        <textarea data-table="yes" title="' . $value->tag_column_name . '" class="form-control normal textArea" data-tablename="' . $table . '" data-tagid="' . $value->tag_object_id . '" data-required="' . $value->tag_column_required . '" wrap="hard" name="' . $this->createColumnFieldName($value->tag_column_name) . '" maxlength="' . $value->tag_column_size . '"></textarea>
                    </div>';
                break;
        }
        return $html;
    }

}
