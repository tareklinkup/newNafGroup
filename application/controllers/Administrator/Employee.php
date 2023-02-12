<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Employee extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->brunch = $this->session->userdata('BRANCHid');
        $access = $this->session->userdata('userId');
        if ($access == '') {
            redirect("Login");
        }
        $this->load->model('Billing_model');
        $this->load->model("Model_myclass", "mmc", TRUE);
        $this->load->model('Model_table', "mt", TRUE);

        $vars['branch_info'] = $this->Billing_model->company_branch_profile($this->brunch);
        $this->load->vars($vars);
    }

    public function index()
    {
        $access = $this->mt->userAccess();
        if(!$access){
            redirect(base_url());
        }
        $data['title'] = "Employee";
        $data['content'] = $this->load->view('Administrator/employee/add_employee', $data, TRUE);
        $this->load->view('Administrator/index', $data);
    }

    public function getEmployees(){
        $employees = $this->db->query("
            select 
                e.*,
                dp.Department_Name,
                ds.Designation_Name,
                concat(e.Employee_ID, ' - ', e.Employee_Name, ' - ', e.Employee_ContactNo) as display_name
            from tbl_employee e 
            join tbl_department dp on dp.Department_SlNo = e.Department_ID
            join tbl_designation ds on ds.Designation_SlNo = e.Designation_ID
            where e.status = 'a'
            and e.Employee_brinchid = ?
        ", $this->session->userdata('BRANCHid'))->result();

        echo json_encode($employees);
    }

    public function getDesignation(){
        $designations = $this->db->query("
            select 
                d.*,
                d.Designation_Name 
                from tbl_designation d
                where d.status = 'a'
        ")->result();

        echo json_encode($designations);
    }

    public function getDepartment(){
        $departments = $this->db->query("
            select 
                ed.*,
                ed.Department_Name 
                from tbl_department ed
                where ed.status = 'a'
        ")->result();

        echo json_encode($departments);
    }

    public function getMonths(){
        $months = $this->db->query("
            select * from tbl_month
        ")->result();

        echo json_encode($months);
    }

    public function getEmployeePayments(){
        $data = json_decode($this->input->raw_input_stream);

        $clauses = "";
        if(isset($data->employeeId) && $data->employeeId != ''){
            $clauses .= " and e.Employee_SlNo = '$data->employeeId'";
        }

        if(isset($data->month) && $data->month != ''){
            $clauses .= " and ep.month_id = '$data->month'";
        }

        if(isset($data->dateFrom) && $data->dateFrom != '' && isset($data->dateTo) && $data->dateTo != ''){
            $clauses .= " and ep.payment_date between '$data->dateFrom' and '$data->dateTo'";
        }

        $payments = $this->db->query("
            select 
                ep.*,
                e.Employee_Name,
                e.Employee_ID,
                e.salary_range,
                dp.Department_Name,
                ds.Designation_Name,
                u.User_Name,
                m.month_name
            from tbl_employee_payment ep
            join tbl_employee e on e.Employee_SlNo = ep.Employee_SlNo
            join tbl_department dp on dp.Department_SlNo = e.Department_ID
            join tbl_designation ds on ds.Designation_SlNo = e.Designation_ID
            join tbl_month m on m.month_id = ep.month_id
            join tbl_user u on u.User_SlNo = ep.save_by
            where ep.paymentBranch_id = ?
            and ep.status = 'a'
            $clauses
            order by ep.employee_payment_id desc
        ", $this->session->userdata('BRANCHid'))->result();

        echo json_encode($payments);
    }

    public function getSalarySummary(){

        $data = json_decode($this->input->raw_input_stream);

        $yearMonth = date("Ym", strtotime($data->monthName));
        // echo '<pre>';
        // print_r($yearMonth);
        // die();
        $summary = $this->db->query("
            select 
                e.*,
                ep.description,
                dp.Department_Name,
                ds.Designation_Name,
                (
                    select ifnull(sum(ep.payment_amount), 0) from tbl_employee_payment ep
                    where ep.Employee_SlNo = e.Employee_SlNo
                    and ep.status = 'a'
                    and ep.month_id = " . $data->monthId . "
                    and ep.paymentBranch_id = " . $this->session->userdata('BRANCHid') . "
                ) as paid_amount,
                
                (
                    select ifnull(sum(ep.deduction_amount), 0) from tbl_employee_payment ep
                    where ep.Employee_SlNo = e.Employee_SlNo
                    and ep.status = 'a'
                    and ep.month_id = " . $data->monthId . "
                    and ep.paymentBranch_id = " . $this->session->userdata('BRANCHid') . "
                ) as deducted_amount,
                
                (
                    select e.salary_range - (paid_amount + deducted_amount)
                ) as due_amount
                
            from tbl_employee e 
            left join tbl_employee_payment ep on ep.Employee_SlNo = e.Employee_SlNo
            left join tbl_department dp on dp.Department_SlNo = e.Department_ID
            left join tbl_designation ds on ds.Designation_SlNo = e.Designation_ID
            where e.status = 'a'
            and '$yearMonth' >= extract(YEAR_MONTH from e.Employee_JoinDate)
            and e.Employee_brinchid = " . $this->session->userdata('BRANCHid') . "
        ")->result();

        echo json_encode($summary);
    }

    public function getPayableSalary(){
        $data = json_decode($this->input->raw_input_stream);

        $payableAmount = $this->db->query("
            select 
            (e.salary_range - ifnull(sum(ep.payment_amount - ep.deduction_amount), 0)) as payable_amount
            from tbl_employee_payment ep
            join tbl_employee e on e.Employee_SlNo = ep.Employee_SlNo
            where ep.status = 'a'
            and ep.month_id = ?
            and ep.Employee_SlNo = ?
            and ep.paymentBranch_id = ?        
        ", [$data->monthId, $data->employeeId, $this->brunch])->row()->payable_amount;

        echo $payableAmount;
    }

    //Designation
    public function designation()
    {
        $access = $this->mt->userAccess();
        if(!$access){
            redirect(base_url());
        }
        $data['title'] = "Add Designation";
        $data['content'] = $this->load->view('Administrator/employee/designation', $data, TRUE);
        $this->load->view('Administrator/index', $data);
    }

    public function insert_designation()
    {
        $mail = $this->input->post('Designation');
        $query = $this->db->query("SELECT Designation_Name from tbl_designation where Designation_Name = '$mail'");

        if ($query->num_rows() > 0) {
            $data['exists'] = "This Name is Already Exists";
            $this->load->view('Administrator/ajax/designation', $data);
        } else {
            $data = array(
                "Designation_Name" => $this->input->post('Designation', TRUE),
                "AddBy" => $this->session->userdata("FullName"),
                "AddTime" => date("Y-m-d H:i:s")
            );
            $this->mt->save_data('tbl_designation', $data);
            //$this->load->view('Administrator/ajax/designation');
        }
    }

    public function designationedit($id)
    {
        $data['title'] = "Edit Designation";
        $fld = 'Designation_SlNo';
        $data['selected'] = $this->Billing_model->select_by_id('tbl_designation', $id, $fld);
        $this->load->view('Administrator/edit/designation_edit', $data);
    }

    public function designationupdate()
    {
        $id = $this->input->post('id');
        $fld = 'Designation_SlNo';
        $data = array(
            "Designation_Name" => $this->input->post('Designation', TRUE),
            "UpdateBy" => $this->session->userdata("FullName"),
            "UpdateTime" => date("Y-m-d H:i:s")
        );
        $this->mt->update_data("tbl_designation", $data, $id, $fld);
    }

    public function designationdelete()
    {
        $fld = 'Designation_SlNo';
        $id = $this->input->post('deleted');
        $this->mt->delete_data("tbl_designation", $id, $fld);
        //$this->load->view('Administrator/ajax/designation');

    }
    //^^^^^^^^^^^^^^^^^^^^^^^^^
    //
    public function depertment()
    {
        $access = $this->mt->userAccess();
        if(!$access){
            redirect(base_url());
        }
        $data['title'] = "Add Depertment";
        $data['content'] = $this->load->view('Administrator/employee/depertment', $data, TRUE);
        $this->load->view('Administrator/index', $data);
    }

    public function insert_depertment()
    {
        $mail = $this->input->post('Depertment');
        $query = $this->db->query("SELECT Department_Name from tbl_department where Department_Name = '$mail'");

        if ($query->num_rows() > 0) {
            $exists = "This Name is Already Exists";
            echo json_encode($exists);
            //$this->load->view('Administrator/ajax/depertment', $data);
        } else {
            $data = array(
                "Department_Name" => $this->input->post('Depertment', TRUE),
                "AddBy" => $this->session->userdata("FullName"),
                "AddTime" => date("Y-m-d H:i:s")
            );
            $this->mt->save_data('tbl_department', $data);
            $message = "Save Successful";
            echo json_encode($message);
        }
    }

    public function depertmentedit($id)
    {
        $data['title'] = "Edit Department";
        $fld = 'Department_SlNo';
        $data['selected'] = $this->Billing_model->select_by_id('tbl_department', $id, $fld);
        $data['content'] = $this->load->view('Administrator/edit/depertment_edit', $data);
        //$this->load->view('Administrator/index', $data);
    }

    public function depertmentupdate()
    {
        $id = $this->input->post('id');
        $fld = 'Department_SlNo';
        $data = array(
            "Department_Name" => $this->input->post('Depertment', TRUE),
            "UpdateBy" => $this->session->userdata("FullName"),
            "UpdateTime" => date("Y-m-d H:i:s")
        );
        $this->mt->update_data("tbl_department", $data, $id, $fld);
    }

    public function depertmentdelete()
    {
        $fld = 'Department_SlNo';
        $id = $this->input->post('deleted');
        $this->mt->delete_data("tbl_department", $id, $fld);
        //$this->load->view('Administrator/ajax/depertment');

    }

    //^^^^^^^^^^^^^^^^^^^^
    public function emplists()
    {
        $access = $this->mt->userAccess();
        if(!$access){
            redirect(base_url());
        }
        $data['title'] = "Employee List";
        $data['employes'] = $this->HR_model->get_all_employee_list();
        $data['content'] = $this->load->view('Administrator/employee/list', $data, TRUE);
        $this->load->view('Administrator/index', $data);
    }

    // fancybox add
    public function fancybox_depertment()
    {
        $this->load->view('Administrator/employee/em_depertment');
    }

    public function fancybox_insert_depertment()
    {
        $mail = $this->input->post('Depertment');
        $query = $this->db->query("SELECT Department_Name from tbl_department where Department_Name = '$mail'");

        if ($query->num_rows() > 0) {
            $data['exists'] = "This Name is Already Exists";
            $this->load->view('Administrator/ajax/fancybox_depertmetn', $data);
        } else {
            $data = array(
                "Department_Name" => $this->input->post('Depertment', TRUE),
                "AddBy" => $this->session->userdata("FullName"),
                "AddTime" => date("Y-m-d H:i:s")
            );
            $this->mt->save_data('tbl_department', $data);
            $this->load->view('Administrator/ajax/fancybox_depertmetn');
        }
    }
    //^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

    // fancybox add 
    public function month()
    {
        $access = $this->mt->userAccess();
        if(!$access){
            redirect(base_url());
        }
        $data['title'] = 'Month';
        $data['content'] = $this->load->view('Administrator/employee/month', $data, TRUE);
        $this->load->view('Administrator/index', $data);
    }

    public function insert_month()
    {
        $month_name = $this->input->post('month');
        $query = $this->db->query("SELECT month_name from tbl_month where month_name = '$month_name'");

        if ($query->num_rows() > 0) {
            $exists = "This Name is Already Exists";
            echo json_encode($exists);
        } else {
            $data = array(
                "month_name" => $this->input->post('month', TRUE),
                /*   "AddBy"                  =>$this->session->userdata("FullName"),
                  "AddTime"                =>date("Y-m-d H:i:s") */
            );
            if ($this->mt->save_data('tbl_month', $data)) {
                $message = "Month insert success";
                echo json_encode($message);
            }
        }
    }

    public function editMonth($id)
    {
        $query = $this->db->query("SELECT * from tbl_month where month_id = '$id'");
        $data['row'] = $query->row();
        $this->load->view('Administrator/employee/edit_month', $data);
    }

    public function updateMonth()
    {
        $id = $this->input->post('month_id');
        $fld = 'month_id';
        $data = array(
            "month_name" => $this->input->post('month', TRUE),
        );
        if ($this->mt->update_data("tbl_month", $data, $id, $fld)) {
            //$message = "Update insert success";
            //echo json_encode($message);
            redirect('month');
        }
    }

    public function fancybox_designation()
    {
        $this->load->view('Administrator/employee/em_designation');
    }

    public function fancybox_insert_designation()
    {
        $mail = $this->input->post('Designation');
        $query = $this->db->query("SELECT Designation_Name from tbl_designation where Designation_Name = '$mail'");

        if ($query->num_rows() > 0) {
            $data['exists'] = "This Name is Already Exists";
            $this->load->view('Administrator/ajax/fancybox_designation', $data);
        } else {
            $data = array(
                "Designation_Name" => $this->input->post('Designation', TRUE),
                "AddBy" => $this->session->userdata("FullName"),
                "AddTime" => date("Y-m-d H:i:s")
            );
            $this->mt->save_data('tbl_designation', $data);
            $this->load->view('Administrator/ajax/fancybox_designation');
        }
    }
    //^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
    // Employee Insert
    public function employee_insert()
    {
        $data = array();
        $this->load->library('upload');
        $config['upload_path'] = './uploads/employeePhoto_org/';
        $config['allowed_types'] = 'gif|jpg|png|jpeg';
        $config['max_size'] = '10000';
        $config['image_width'] = '4000';
        $config['image_height'] = '4000';
        $this->upload->initialize($config);

        $data['Designation_ID'] = $this->input->post('em_Designation', true);
        $data['bio_number'] = $this->input->post('bio_number', true);
        $data['Department_ID'] = $this->input->post('em_Depertment', true);
        $data['Employee_ID'] = $this->input->post('Employeer_id', true);
        $data['Employee_Name'] = $this->input->post('em_name', true);
        $data['Employee_JoinDate'] = $this->input->post('em_Joint_date');
        $data['Employee_Gender'] = $this->input->post('Gender', true);
        $data['Employee_BirthDate'] = $this->input->post('em_dob', true);
        $data['Employee_ContactNo'] = $this->input->post('em_contact', true);
        $data['ref_name'] = $this->input->post('ref_name', true);
        $data['ref_contact'] = $this->input->post('ref_contact', true);
        $data['Employee_Email'] = $this->input->post('ec_email', true);
        $data['Employee_MaritalStatus'] = $this->input->post('Marital', true);
        $data['Employee_FatherName'] = $this->input->post('em_father', true);
        $data['Employee_MotherName'] = $this->input->post('mother_name', true);
        $data['Employee_PrasentAddress'] = $this->input->post('em_Present_address', true);
        $data['Employee_PermanentAddress'] = $this->input->post('em_Permanent_address', true);
        $data['salary_range'] = $this->input->post('salary_range', true);
        $data['status'] = 'a';

        $data['AddBy'] = $this->session->userdata("FullName");
        $data['Employee_brinchid'] = $this->session->userdata("BRANCHid");
        $data['AddTime'] = date("Y-m-d H:i:s");

        $this->upload->do_upload('em_photo');
        $images = $this->upload->data();
        $data['Employee_Pic_org'] = $images['file_name'];

        $config['image_library'] = 'gd2';
        $config['source_image'] = $this->upload->upload_path . $this->upload->file_name;
        $config['new_image'] = 'uploads/' . 'employeePhoto_thum/' . $this->upload->file_name;
        $config['maintain_ratio'] = FALSE;
        $config['width'] = 165;
        $config['height'] = 175;
        $this->load->library('image_lib', $config);
        $this->image_lib->resize();
        $data['Employee_Pic_thum'] = $this->upload->file_name;
        //echo "<pre>";print_r($data);exit;
        $this->mt->save_data('tbl_employee', $data);
        //$this->Billing_model->save_employee_data($data);
        //redirect('Administrator/Employee/');
        //$this->load->view('Administrator/ajax/add_employee');
    }

    public function employee_edit($id)
    {
        $data['title'] = "Edit Employee";
        $query = $this->db->query("SELECT tbl_employee.*,tbl_department.*,tbl_designation.* FROM tbl_employee left join tbl_department on tbl_department.Department_SlNo=tbl_employee.Department_ID left join tbl_designation on tbl_designation.Designation_SlNo=tbl_employee.Designation_ID  where tbl_employee.Employee_SlNo = '$id'");
        $data['selected'] = $query->row();
        //echo "<pre>";print_r($data['selected']);exit;
        $data['content'] = $this->load->view('Administrator/edit/employee_edit', $data, TRUE);
        $this->load->view('Administrator/index', $data);
    }

    public function employee_Update()
    {
        try {
        
            $id = $this->input->post('iidd');
            $fld = 'Employee_SlNo';
            $this->load->library('upload');
            $config['upload_path'] = './uploads/employeePhoto_org/';
            $config['allowed_types'] = 'gif|jpg|png|jpeg';
            $config['max_size'] = '10000';
            $config['image_width'] = '4000';
            $config['image_height'] = '4000';
            $this->upload->initialize($config);

            $data['Designation_ID'] = $this->input->post('em_Designation', true);
            $data['Department_ID'] = $this->input->post('em_Depertment', true);
            $data['Employee_ID'] = $this->input->post('Employeer_id', true);
            $data['Employee_Name'] = $this->input->post('em_name', true);
            $data['Employee_JoinDate'] = $this->input->post('em_Joint_date');
            $data['Employee_Gender'] = $this->input->post('Gender', true);
            $data['Employee_BirthDate'] = $this->input->post('em_dob', true);
            $data['Employee_ContactNo'] = $this->input->post('em_contact', true);
            $data['Employee_Email'] = $this->input->post('ec_email', true);
            $data['Employee_MaritalStatus'] = $this->input->post('Marital', true);
            $data['Employee_FatherName'] = $this->input->post('em_father', true);
            $data['Employee_MotherName'] = $this->input->post('mother_name', true);
            $data['Employee_PrasentAddress'] = $this->input->post('em_Present_address', true);
            $data['Employee_PermanentAddress'] = $this->input->post('em_Permanent_address', true);
            $data['Employee_brinchid'] = $this->session->userdata("BRANCHid");
            $data['salary_range'] = $this->input->post('salary_range', true);

            $data['UpdateBy'] = $this->session->userdata("FullName");
            $data['UpdateTime'] = date("Y-m-d H:i:s");

        //    $xx = $this->mt->select_by_id("tbl_employee", $id, $fld);
            $xx = $this->db->query("SELECT * FROM `tbl_employee` WHERE `Employee_SlNo` = $id ")->row();
            $image = $this->upload->do_upload('em_photo');
            $images = $this->upload->data();
// echo $xx->Employee_Pic_org;
            if ($image != "") {
                if ($xx->Employee_Pic_thum != '' && $xx->Employee_Pic_org != '') {
                    unlink("./uploads/employeePhoto_thum/" . $xx->Employee_Pic_thum);
                    unlink("./uploads/employeePhoto_org/" . $xx->Employee_Pic_org);
                }
                $data['Employee_Pic_org'] = $images['file_name'];

                $config['image_library'] = 'gd2';
                $config['source_image'] = $this->upload->upload_path . $this->upload->file_name;
                $config['new_image'] = 'uploads/employeePhoto_thum/' . $this->upload->file_name;
                $config['maintain_ratio'] = FALSE;
                $config['width'] = 165;
                $config['height'] = 175;
                $this->load->library('image_lib', $config);
                $this->image_lib->resize();
                $data['Employee_Pic_thum'] = $this->upload->file_name;
            } else {
                $data['Employee_Pic_org'] = $xx->Employee_Pic_org;
                $data['Employee_Pic_thum'] = $xx->Employee_Pic_thum;
            }

            $this->mt->update_data("tbl_employee", $data, $id, $fld);
            $res = ['success'=>true, 'message'=>'Employee updated'];
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage());
        }
        echo json_encode($res);
    }

    public function employee_Delete()
    {
        $id = $this->input->post('deleted');
        $this->db->set(['status'=>'d'])->where('Employee_SlNo', $id)->update('tbl_employee');
    }

    public function active()
    {
        // $fld = 'Employee_SlNo';
        // $id = $this->input->post('deleted');
        // $this->mt->active("tbl_employee", $id, $fld);
        $id = $this->input->post('deleted');
        $this->db->set(['status'=>'a'])->where('Employee_SlNo', $id)->update('tbl_employee');
        // $this->load->view('Administrator/ajax/employee_list');
    }

    public function employeesalarypayment()
    {
        $access = $this->mt->userAccess();
        if(!$access){
            redirect(base_url());
        }
        $data['title'] = "Employee Salary Payment";
        $data['content'] = $this->load->view('Administrator/employee/employee_salary', $data, TRUE);
        $this->load->view('Administrator/index', $data);
    }

    public function selectEmployee()
    {
        $data['title'] = "Employee Salary Payment";
        $employee_id = $this->input->post('employee_id');
        $query = $this->db->query("SELECT `salary_range` FROM tbl_employee where Employee_SlNo='$employee_id'");
        $data['employee'] = $query->row();
        $this->load->view('Administrator/employee/ajax_employeey', $data);
    }

    public function addEmployeePayment()
    {
        $res = ['success'=>false, 'message'=>'Nothing happened'];
        try{
            $paymentObj = json_decode($this->input->raw_input_stream);
            $payment = (array)$paymentObj;
            unset($payment['employee_payment_id']);
            $payment['status'] = 'a';
            $payment['save_by'] = $this->session->userdata('userId');
            $payment['save_date'] = Date('Y-m-d H:i:s');
            $payment['paymentBranch_id'] = $this->brunch;

            $this->db->insert('tbl_employee_payment', $payment);
            $res = ['success'=>true, 'message'=>'Employee payment added'];
        } catch (Exception $ex){
            throw new Exception($ex->getMessage());
        }

        echo json_encode($res);
    }

    public function employeesalaryreport()
    {
        $access = $this->mt->userAccess();
        if(!$access){
            redirect(base_url());
        }
        $data['title'] = "Employee Salary Report";
        $data['content'] = $this->load->view('Administrator/employee/employee_salary_report', $data, TRUE);
        $this->load->view('Administrator/index', $data);
    }

    public function EmployeeSalary_list()
    {
        $datas['employee_id'] = $employee_id = $this->input->post('employee_id');
        $datas['month'] = $month = $this->input->post('month');

        $this->session->set_userdata($datas);

        $BRANCHid = $this->session->userdata("BRANCHid");

        if ($employee_id == 'All') {

            $employeequery = $this->db
                ->join('tbl_designation', 'tbl_designation.Designation_SlNo=tbl_employee.Designation_ID', 'left')
                ->where('tbl_employee.Employee_brinchid', $BRANCHid)
                ->get('tbl_employee')->result();
            $data['employee_list'] = $employeequery;


        } else {


            $employeequery = $this->db
                ->join('tbl_designation', 'tbl_designation.Designation_SlNo=tbl_employee.Designation_ID', 'left')
                ->where('tbl_employee.Employee_brinchid', $BRANCHid)
                ->where('tbl_employee.Employee_SlNo	', $employee_id)
                ->get('tbl_employee')->result();
            $data['employee_list'] = $employeequery;
        }

        $data['month'] = $month;
        $this->load->view('Administrator/employee/employee_salary_report_list', $data);
    }

    public function EmploeePaymentReportPrint()
    {
        $BRANCHid = $this->session->userdata("BRANCHid");

        $employee_id = $this->session->userdata('employee_id');
        $month = $this->session->userdata('month');

        if ($employee_id == 'All') {

            $employeequery = $this->db
                ->join('tbl_designation', 'tbl_designation.Designation_SlNo=tbl_employee.Designation_ID', 'left')
                ->where('tbl_employee.Employee_brinchid', $BRANCHid)
                ->get('tbl_employee')->result();
            $data['employee_list'] = $employeequery;


        } else {

            $employeequery = $this->db
                ->join('tbl_designation', 'tbl_designation.Designation_SlNo=tbl_employee.Designation_ID', 'left')
                ->where('tbl_employee.Employee_brinchid', $BRANCHid)
                ->where('tbl_employee.Employee_SlNo	', $employee_id)
                ->get('tbl_employee')->result();
            $data['employee_list'] = $employeequery;
        }

        $data['month'] = $month;
        $this->load->view('Administrator/employee/employee_salary_report_print', $data);
    }

    public function edit_employee_salary($id)
    {
        $data['title'] = "Edit Employee Salary";
        $BRANCHid = $this->session->userdata("BRANCHid");
        $query = $this->db->query("SELECT tbl_employee.*,tbl_employee_payment.*,tbl_month.*,tbl_designation.* FROM tbl_employee left join tbl_employee_payment on tbl_employee_payment.Employee_SlNo=tbl_employee.Employee_SlNo left join tbl_month on tbl_employee_payment.month_id=tbl_month.month_id left join tbl_designation on tbl_designation.Designation_SlNo=tbl_employee.Designation_ID where tbl_employee_payment.employee_payment_id='$id' AND tbl_employee_payment.paymentBranch_id='$BRANCHid'");
        $data['selected'] = $query->row();
        //echo "<pre>";print_r($data['selected']);exit;
        $data['content'] = $this->load->view('Administrator/employee/edit_employee_salary', $data, TRUE);
        $this->load->view('Administrator/index', $data);
    }

    public function updateEmployeePayment()
    {
        $res = ['success'=>false, 'message'=>'Nothing happened'];
        try{
            $paymentObj = json_decode($this->input->raw_input_stream);
            $payment = (array)$paymentObj;
            unset($payment['employee_payment_id']);
            $payment['update_by'] = $this->session->userdata('userId');
            $payment['update_date'] = Date('Y-m-d H:i:s');

            $this->db->where('employee_payment_id', $paymentObj->employee_payment_id)->update('tbl_employee_payment', $payment);
            $res = ['success'=>true, 'message'=>'Employee payment updated'];
        } catch (Exception $ex){
            throw new Exception($ex->getMessage());
        }

        echo json_encode($res);
    }

    public function deleteEmployeePayment(){
        $res = ['success'=>false, 'message'=>'Nothing happened'];
        try{
            $data = json_decode($this->input->raw_input_stream);

            $this->db->set(['status'=>'d'])->where('employee_payment_id', $data->paymentId)->update('tbl_employee_payment');
            $res = ['success'=>true, 'message'=>'Employee payment deleted'];
        } catch (Exception $ex){
            throw new Exception($ex->getMessage());
        }

        echo json_encode($res);
    }

    public function daily_attendence(){
        $access = $this->mt->userAccess();
        if(!$access){
            redirect(base_url());
        }
        $data['fullName'] = $this->session->userdata('FullName');
        $data['userId'] = $this->session->userdata('userId');
        $data['title'] = "Daily Attendence";
        $data['content'] = $this->load->view('Administrator/employee/daily_attendence', $data, TRUE);
        $this->load->view('Administrator/index', $data);
    }
    public function update_attendence(){
        
        $data = json_decode($this->input->raw_input_stream);

        $input_type = $data->value;
        try{
            $status = 'p';
            $date = Date('Y-m-d');
            $time = date("h:i:s A");
            $userId = $this->session->userdata('userId');
            $branch_id = $this->session->userdata("BRANCHid");

            if($input_type == 'a_in') {
                $this->db->query("INSERT INTO `tbl_employee_attendence`(`date`, `attendence_in`, `status`, `branch_id`,`user_id`)
                VALUES ('$date','$time','$status','$branch_id','$userId')");

                $res = ['success'=>true, 'message'=>'Attendence In Success'];
            }
            if($input_type == 'a_out') {
                $this->db->query("UPDATE `tbl_employee_attendence` SET `attendence_out`='$time'
                 WHERE `date`= ? AND `branch_id`= ? AND `user_id`= ?", [$date,$this->session->userdata("BRANCHid"),$userId ]);

                $res = ['success'=>true, 'message'=>'Attendence Out Success'];
            }
            if($input_type == 'l_out') {
                $this->db->query("UPDATE `tbl_employee_attendence` SET `lunch_out`='$time'
                 WHERE `date`= ? AND `branch_id`= ? AND `user_id`= ?", [$date,$this->session->userdata("BRANCHid"),$userId]);

                $res = ['success'=>true, 'message'=>'Attendence Out Success'];
            }
            if($input_type == 'l_in') {
                $this->db->query("UPDATE `tbl_employee_attendence` SET `lunch_in`='$time'
                 WHERE `date`= ? AND `branch_id`= ? AND `user_id`= ?", [$date,$this->session->userdata("BRANCHid"),$userId]);

                $res = ['success'=>true, 'message'=>'Attendence Out Success'];
            }
            // $attendence = (array)$data;
            // $attendence['status'] = 'p';
            // $attendence['date'] = Date('Y-m-d');
            // $attendence['attendence_in'] = date("h:i:sa");
            // $attendence['branch_id'] = $this->session->userdata("BRANCHid");
            
            // $this->db->insert('tbl_employee_attendence', $attendence);
            // if($input == 'a_in'){
            //     $this->db->insert();
            //     $this->db->insert('tbl_employee_attendence', $attendence);
            //     $res = ['success'=>true, 'message'=>'Attendence Success'];
            // }
        } catch (Exception $ex){
            throw new Exception($ex->getMessage());
        }
        
        echo json_encode($res);
        
    }
    public function get_attendence(){

        // $date = Date('Y-m-d');
        $userId = $this->session->userdata('userId');

        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');

        $result = $this->db->query("SELECT tea.*, tu.FullName
        FROM `tbl_employee_attendence` tea
        INNER JOIN tbl_user tu ON tu.User_SlNo = tea.user_id
        WHERE tea.`date` BETWEEN '$start_date' and '$end_date'
        and tea.`branch_id` = ?
        and tea.`user_id` = ?
        ORDER BY `tea`.`id`  DESC",[$this->session->userdata("BRANCHid"), $userId])->result();

        echo json_encode($result);
        
    }

    public function get_all_attendence(){

        $date = Date('Y-m-d');

        $result = $this->db->query("SELECT tea.*,
        tea.status as db_status,
        CASE
                WHEN tea.status = 'a' THEN 'Present'
                WHEN tea.status = 'p' THEN 'Pending'
                WHEN tea.status = 'r' THEN 'Rejected'
        END AS status,
        tb.Brunch_name, tu.FullName
        FROM tbl_employee_attendence tea
        INNER JOIN tbl_brunch tb ON tb.brunch_id = tea.branch_id
        INNER JOIN tbl_user tu ON tu.User_SlNo = tea.user_id
        ORDER BY `tea`.`id` DESC")->result();

        echo json_encode($result);
        
    }

    public function pending_attendence(){
        $access = $this->mt->userAccess();
        if(!$access){
            redirect(base_url());
        }
        $data['title'] = "Pending Attendence";
        $data['content'] = $this->load->view('Administrator/employee/pending_attendence', $data, TRUE);
        $this->load->view('Administrator/index', $data);
    }

    public function update_status_attendence(){
        $data = json_decode($this->input->raw_input_stream);
        $id = $data->id;
        $status = $data->status;

        // $this->db->query("UPDATE `tbl_employee_attendence` SET `status`='$status', `ApproveBy`='$this->session->userdata('FullName')'
        //     WHERE `id`= ?", [$id]);

        $this->db->set('status', $status)->where('id', $id)->update('tbl_employee_attendence');
        $this->db->set('ApproveBy', $this->session->userdata("FullName"))->where('id', $id)->update('tbl_employee_attendence');

        $res = ['success'=>true, 'message'=>'Status Update Success'];

        echo json_encode($res);
    }


    public function update_reject_status(){
        $data = json_decode($this->input->raw_input_stream);
        $id = $data->id;
        $status = $data->status;
        $this->db->set('status', $status)->where('id', $id)->update('tbl_employee_attendence');
        $res = ['success'=>true, 'message'=>'Status Update Success'];

        echo json_encode($res);
    }



    
    public function  delete_status_attendence(){
        $data = json_decode($this->input->raw_input_stream);
        $id = $data->id;       
        $this->db->query("DELETE FROM `tbl_employee_attendence` WHERE `id`= ?", [$id]);

        $res = ['success'=>true, 'message'=>'Reject Success'];

        echo json_encode($res);
    }

    public function leaveEntry($id = null){
        $access = $this->mt->userAccess();
        if(!$access){
            redirect(base_url());
        }
        $data['title'] = "Leave Entry";
        $data['leave_id'] = $id;
        $data['content'] = $this->load->view('Administrator/employee/leave_entry', $data, TRUE);
        $this->load->view('Administrator/index', $data);
    }

    public function leavePending($id = null){
        $access = $this->mt->userAccess();
        if(!$access){
            redirect(base_url());
        }
        $data['title'] = "Leave Pending";
        $data['leave_id'] = $id;
        $data['content'] = $this->load->view('Administrator/employee/leave_pending', $data, TRUE);
        $this->load->view('Administrator/index', $data);
    }


    public function saveLeaveEntry()
    {
        
        $dataObj = json_decode($this->input->raw_input_stream);

        $leaveId = $dataObj->leave_SlNo;

        try {
            $data = (array)$dataObj;
            unset($data['leave_SlNo']);
            unset($data['leave_id']);

            if($leaveId == ''){
                $data['status']    = 'p';
                $data['AddBy']     = $this->session->userdata("userId");
                $data['AddTime']   = date('Y-m-d H:i:s');
                $data['branch_id'] = $this->brunch;

                $this->db->insert('tbl_emp_leave', $data);
     
                $res = ['success' => true, 'message' => 'Data save successfully'];

            }else {

                $data['UpdateBy']   = $this->session->userdata("userId");
                $data['updatetime'] = date('Y-m-d H:i:s');
                $data['branch_id']  = $this->brunch;

                $this->db->where('leave_SlNo',$leaveId)->update('tbl_emp_leave', $data);

                $res = ['success' => true, 'message' => 'Data Update successfully'];
            } 

        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }
        
        echo json_encode($res);
        
    }

    public function deleteLeaveEntry()
    {
        $res = ['success' => false, 'message' => ''];

        $leaveId = json_decode($this->input->raw_input_stream);

        try {
            $this->db->set(['status' => 'd'])->where(['leave_SlNo' => $leaveId->leave_id, 'branch_id' => $this->brunch])->update('tbl_emp_leave');
            $res = ['success' => true, 'message' => 'Item deleted successfully'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }


    public function leaveEntryReport(){
        $access = $this->mt->userAccess();
        if(!$access){
            redirect(base_url());
        }
        $data['title'] = "Leave Record";
        $data['content'] = $this->load->view('Administrator/employee/leave_record', $data, TRUE);
        $this->load->view('Administrator/index', $data);
    }

    public function getLeaveReport()
    {
        $data = json_decode($this->input->raw_input_stream);
        
        $clauses = "";
        
        if(isset($data->dateFrom) && $data->dateFrom != '' && isset($data->dateTo) && $data->dateTo != ''){
            $clauses .= " and el.date between '$data->dateFrom' and '$data->dateTo'";
        }
        
        if (isset($data->UserId) && $data->UserId != '') {
            $clauses .= " and el.User_ID = '$data->UserId'";
        }

        $res = $this->db->query("
                select 
                    el.*,
                    u.User_Name,       
                    n.description as display_note
                from tbl_emp_leave el
                join tbl_user u on u.User_SlNo = el.User_ID
                left join tbl_leave_note n on n.Note_SlNo = el.note_id
                where el.branch_id = ?
                $clauses
            ", $this->brunch)->result();
            
        echo json_encode($res);
    }



    public function getLeaveDetails(){
        
        $data = json_decode($this->input->raw_input_stream);

        $clauses = '';
        if (isset($data->leave_id) && $data->leave_id != '') {
            $clauses .= " and el.leave_SlNo = '$data->leave_id'";
        }

        $leaves = $this->db->query("select el.*,
            u.User_Name,
            n.description as display_note
            from tbl_emp_leave el
            left join tbl_user u on u.User_SlNo = el.User_ID
            left join tbl_leave_note n on n.Note_SlNo = el.note_id
            where el.status != 'd'
            ORDER BY el.leave_SlNo DESC
            $clauses
        ")->result();
        
        echo json_encode($leaves);
        
    }

    public function getLeaveEdit(){
        
        $data = json_decode($this->input->raw_input_stream);

        $leaves = $this->db->query("select el.*,
            e.Employee_Name,
            d.Designation_Name,
            ed.Department_Name,
            n.description as display_note
            from tbl_emp_leave el
            left join tbl_employee e on e.Employee_SlNo = el.Employee_ID
            left join tbl_designation d on d.Designation_SlNo = e.Designation_ID
            left join tbl_department ed on ed.Department_SlNo  = e.Department_ID
            left join tbl_leave_note n on n.Note_SlNo = el.note_id
            where el.leave_SlNo = '$data->leave_id'
            and el.status != 'd'
        ")->result();
        
        echo json_encode($leaves);
        
    }


    public function updateStatusLeave()
    {
        $data = json_decode($this->input->raw_input_stream);
        $id = $data->leave_id;
        $status = $data->status;

        $this->db->query("UPDATE `tbl_emp_leave` SET `status`='$status'
            WHERE `leave_SlNo`= ?", [$id]);

        $res = ['success'=>true, 'message'=>'Approve Success'];

        echo json_encode($res);
    }

    public function attendence_record(){
        $access = $this->mt->userAccess();
        if(!$access){
            redirect(base_url());
        }
        $data['title'] = "Attendence Record";
        $data['content'] = $this->load->view('Administrator/employee/attendence_record', $data, TRUE);
        $this->load->view('Administrator/index', $data);
    }


    public function search_attendence(){
        $data = json_decode($this->input->raw_input_stream);

        $clauses = "";
        if(isset($data->userId) && $data->userId != ''){
            $clauses .= "tea.user_id = '$data->userId' and ";
        }

        if(isset($data->brunchId) && $data->brunchId != ''){
            $clauses .= "tea.branch_id = '$data->brunchId' and ";
        }
        
        if(isset($data->dateFrom) && $data->dateFrom != '' && isset($data->dateTo) && $data->dateTo != ''){
            $clauses .= "tea.date between '$data->dateFrom' and '$data->dateTo'";
        }

        $record = $this->db->query(
            "SELECT tea.*,
            tea.status as db_status,
            CASE
                WHEN tea.status = 'a' THEN 'Approved'
                WHEN tea.status = 'p' THEN 'Pending'
                WHEN tea.status = 'r' THEN 'Rejected'
            END AS status,
            tb.Brunch_name,
            tb.Brunch_title,
            tu.FullName,
            tu.User_Name
                FROM `tbl_employee_attendence` tea
                INNER JOIN tbl_brunch tb on tb.brunch_id = tea.branch_id
                INNER JOIN tbl_user tu on tu.User_SlNo = tea.user_id
                WHERE $clauses
            ")->result();

        echo json_encode($record);
    }

}