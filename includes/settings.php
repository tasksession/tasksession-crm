<?php
// If it's going to need the database, then it's 
// probably smart to require it before we start.
require_once('database.php');

class Settings extends DatabaseObject {
	
	protected static $tblName="settings";
	protected static $tblFields = array('url', 'company_name', 'company_address', 'default_invoice_memo', 'default_invoice_footer', 'syatem_title', 'login_page_title', 'copy_rights', 'system_currency', 'multiple_currencies', 'time_zone', 'favicon_image', 'login_page_logo', 'logo', 'mobile_logo', 'login_page_image', 'invoice_logo', 'email_template_logo', 'stripe_sk', 'stripe_pk', 'paypal_email', 'checkout_id', 'checkout_pk','system_email', 'forget_email', 'create_account_email', 'project_assign_email', 'assign_staff_email', 'project_update_email', 'task_create_email', 'task_update_email', 'task_update_email_on_drag', 'invoice_create_email', 'invoice_paid_email', 'invoice_paid_email_admin', 'message_notification_email', 'message_notification_email_subject', 'group_chat_create_email', 'group_chat_create_email_subject', 'group_chat_message_email', 'group_chat_message_email_subject', 'group_chat_batch_email', 'group_chat_batch_email_subject', 'one_to_one_chat_batch_email', 'one_to_one_chat_batch_email_subject', 'task_chat_batch_email', 'task_chat_batch_email_subject', 'payment_reminders_enabled', 'payment_reminder_1_enabled', 'payment_reminder_1_days', 'payment_reminder_1_type', 'payment_reminder_1_subject', 'payment_reminder_1_template', 'payment_reminder_2_enabled', 'payment_reminder_2_days', 'payment_reminder_2_type', 'payment_reminder_2_subject', 'payment_reminder_2_template', 'payment_reminder_3_enabled', 'payment_reminder_3_days', 'payment_reminder_3_type', 'payment_reminder_3_subject', 'payment_reminder_3_template', 'payment_reminder_send_to_client', 'payment_reminder_send_to_staff', 'payment_reminder_notify_admin_in_app', 'task_reminders_enabled', 'task_reminder_1_enabled', 'task_reminder_1_days', 'task_reminder_1_type', 'task_reminder_1_subject', 'task_reminder_1_template', 'task_reminder_2_enabled', 'task_reminder_2_days', 'task_reminder_2_type', 'task_reminder_2_subject', 'task_reminder_2_template', 'task_reminder_3_enabled', 'task_reminder_3_days', 'task_reminder_3_type', 'task_reminder_3_subject', 'task_reminder_3_template', 'task_reminder_send_to_staff', 'task_reminder_send_to_client', 'task_reminder_send_to_creator', 'system_language', 'version', 'purchase_code', 'chat_refresh_interval', 'chat_email_notify_admin', 'chat_email_notify_staff', 'chat_email_notify_client', 'chat_email_notify_mention', 'presence_toast_enabled', 'presence_beep_enabled', 'presence_staff_toast_enabled', 'browser_push_enabled', 'use_smtp', 'smtp_from_name', 'smtp_from_email', 'smtp_username', 'smtp_host', 'smtp_port', 'smtp_password', 'smtp_secure', 'default_sales_tax', 'module_lead_board', 'module_invoices', 'module_projects', 'module_tasks', 'module_file_management', 'module_notes_documents', 'module_discussions', 'module_email', 'module_marketing', 'module_ai', 'module_attendance', 'module_ip_restriction', 'module_ecommerce', 'global_allowed_ips', 'module_time_tracking', 'module_reports', 'email_report_enabled', 'email_report_template', 'email_report_subject', 'email_report_frequency', 'label_projects_override', 'label_tasks_override', 'label_clients_override', 'label_financials_override', 'label_chatting_override', 'label_private_notes_override', 'label_leads_override', 'label_media_vault_override', 'label_custom_fields_override', 'label_event_override', 'sidebar_menu_order', 'sidebar_menu_hidden', 'admin_login_landing_page', 'staff_login_landing_page', 'setup_guide_pending', 'vapid_public_key', 'vapid_private_key');
	
	public $url;
	public $company_name;
	public $company_address;
	public $default_invoice_memo = '';
	public $default_invoice_footer = '';
	public $syatem_title;
	public $login_page_title;
	public $copy_rights;
	public $system_currency;
	public $multiple_currencies = '';
	public $time_zone;
	public $favicon_image;
	public $login_page_logo;
	public $logo;
	public $mobile_logo;
	public $login_page_image;
	public $invoice_logo;
	public $email_template_logo = '';
	public $stripe_sk;
	public $stripe_pk;
	public $paypal_email;
	public $checkout_id;
	public $checkout_pk;
	public $system_email;
	public $forget_email;
	public $create_account_email;
	public $project_assign_email;
	public $assign_staff_email;
	public $project_update_email;
	public $task_create_email;
	public $task_update_email;
	public $task_update_email_on_drag = 1;
	public $invoice_create_email;
	public $invoice_paid_email;
	public $invoice_paid_email_admin;
	public $message_notification_email;
	public $message_notification_email_subject = 'You have received a new message';
	public $group_chat_create_email;
	public $group_chat_create_email_subject = 'You have been added to group: {GROUP_NAME}';
	public $group_chat_message_email;
	public $group_chat_message_email_subject = 'You have {MESSAGE_COUNT} new message';
	public $group_chat_batch_email;
	public $group_chat_batch_email_subject = 'You have {MESSAGE_COUNT} new message(s) in {GROUP_NAME}';
	public $one_to_one_chat_batch_email;
	public $one_to_one_chat_batch_email_subject = 'You have {MESSAGE_COUNT} new message(s) from {SENDER_NAME}';
	public $task_chat_batch_email;
	public $task_chat_batch_email_subject = 'You have {MESSAGE_COUNT} new message(s) in task: {TASK_NAME}';
	
	// Payment Reminder Settings
	public $payment_reminders_enabled = 0;
	public $payment_reminder_1_enabled = 0;
	public $payment_reminder_1_days = 7;
	public $payment_reminder_1_type = 'before';
	public $payment_reminder_1_subject = '';
	public $payment_reminder_1_template = '';
	public $payment_reminder_2_enabled = 0;
	public $payment_reminder_2_days = 3;
	public $payment_reminder_2_type = 'after';
	public $payment_reminder_2_subject = '';
	public $payment_reminder_2_template = '';
	public $payment_reminder_3_enabled = 0;
	public $payment_reminder_3_days = 14;
	public $payment_reminder_3_type = 'after';
	public $payment_reminder_3_subject = '';
	public $payment_reminder_3_template = '';
	public $payment_reminder_send_to_client = 1;
	public $payment_reminder_send_to_staff = 0;
	public $payment_reminder_notify_admin_in_app = 1;
	
	// Task Reminder Settings
	public $task_reminders_enabled = 0;
	public $task_reminder_1_enabled = 0;
	public $task_reminder_1_days = 7;
	public $task_reminder_1_type = 'before';
	public $task_reminder_1_subject = '';
	public $task_reminder_1_template = '';
	public $task_reminder_2_enabled = 0;
	public $task_reminder_2_days = 3;
	public $task_reminder_2_type = 'after';
	public $task_reminder_2_subject = '';
	public $task_reminder_2_template = '';
	public $task_reminder_3_enabled = 0;
	public $task_reminder_3_days = 14;
	public $task_reminder_3_type = 'after';
	public $task_reminder_3_subject = '';
	public $task_reminder_3_template = '';
	public $task_reminder_send_to_staff = 1;
	public $task_reminder_send_to_client = 0;
	public $task_reminder_send_to_creator = 0;
	
	public $system_language;
	public $version;
	public $purchase_code;
	public $chat_refresh_interval;
	public $chat_email_notify_admin = 1;
	public $chat_email_notify_staff = 1;
	public $chat_email_notify_client = 1;
	public $chat_email_notify_mention = 1;
	public $presence_toast_enabled = 1;
	public $presence_beep_enabled = 1;
	public $presence_staff_toast_enabled = 1;
	public $browser_push_enabled = 1;
	public $setup_guide_pending = 0;
	public $vapid_public_key = '';
	public $vapid_private_key = '';
	
	// SMTP Settings
	public $use_smtp;
	public $smtp_from_name;
	public $smtp_from_email;
	public $smtp_username;
	public $smtp_host;
	public $smtp_port;
	public $smtp_password;
	public $smtp_secure;
	public $default_sales_tax;
	
	// Module toggles
	public $module_lead_board = 1;
	public $module_invoices = 1;
	public $module_projects = 1;
	public $module_tasks = 1;
	public $module_file_management = 1;
	public $module_notes_documents = 1;
	public $module_discussions = 1;
	public $module_email = 1;
	public $module_marketing = 0;
	public $module_ai = 0;
	public $module_attendance = 1;
	public $module_ip_restriction = 1;
	public $module_ecommerce = 0;
	public $global_allowed_ips = null;
	public $module_time_tracking = 1;
	public $module_reports = 1;

	// Email Report Settings
	public $email_report_enabled = 0;
	public $email_report_template = '';
	public $email_report_subject = 'Email Account Summary Report';
	public $email_report_frequency = '24hours';

	// Menu label override settings
	public $label_projects_override = '';
	public $label_tasks_override = '';
	public $label_clients_override = '';
	public $label_financials_override = '';
	public $label_chatting_override = '';
	public $label_private_notes_override = '';
	public $label_leads_override = '';
	public $label_media_vault_override = '';
	public $label_custom_fields_override = '';
	public $label_event_override = '';

	// Sidebar menu layout
	public $sidebar_menu_order = '';
	public $sidebar_menu_hidden = '';

	// Login landing pages (relative paths)
	public $admin_login_landing_page = 'admin/index.php';
	public $staff_login_landing_page = 'staff/index.php';
	
	public $message=NULL;	
	public $id;
	
	
public static function findById($useId="") {
    global $database;
		$sql  = "SELECT * FROM settings ";
		$sql .= "WHERE id = '{$useId}' ";
		$sql .= "LIMIT 1";
		$result_array = self::findBySql($sql);  // $result_array is an object
		
		return !empty($result_array) ? array_shift($result_array) : false;
			
	}
public $currency_symbols = array(
	'AUD,$' => 'AUD($)',
	'CAD,$' => 'CAD($)',
	'GBP,£' => 'GBP(£)',
	'NZD,$' => 'NZD($)',
	'EUR,€' => 'EUR(€)',
	'USD,$' => 'USD($)',
	'Rs,Rs' => 'Rs(Rs)',
	'INR,₹' => 'INR(₹)',
	'JPY,¥' => 'JPY(¥)',
	'CHF,CHF' => 'CHF(CHF)',
	'SEK,SEK' => 'SEK(SEK)',
	'KRW,₩' => 'KRW(₩)',
	'SGD,S$' => 'SGD(S$)',
	'HKD,HK$' => 'HKD(HK$)',
	'CNY,¥' => 'CNY(¥)',
	'BRL,R$' => 'BRL(R$)',
	'RUB,₽' => 'RUB(₽)',
	'ZAR,R' => 'ZAR(R)',
	'TRY,₺' => 'TRY(₺)',
	'PLN,zł' => 'PLN(zł)',
	'THB,฿' => 'THB(฿)',
	'MYR,RM' => 'MYR(RM)',
	'IDR,Rp' => 'IDR(Rp)',
	'PHP,₱' => 'PHP(₱)',
	'VND,₫' => 'VND(₫)',
	'BDT,৳' => 'BDT(৳)',
	'EGP,E£' => 'EGP(E£)',
	'NGN,₦' => 'NGN(₦)',
	'KES,KSh' => 'KES(KSh)',
	'GHS,GH₵' => 'GHS(GH₵)',
	'UGX,USh' => 'UGX(USh)',
	'TZS,TSh' => 'TZS(TSh)',
	'ZMW,K' => 'ZMW(K)',
	'BWP,P' => 'BWP(P)',
	'NAD,N$' => 'NAD(N$)',
	'LSL,L' => 'LSL(L)',
	'SZL,L' => 'SZL(L)',
	'BIF,FBu' => 'BIF(FBu)',
	'RWF,FRw' => 'RWF(FRw)',
	'DJF,Fdj' => 'DJF(Fdj)',
	'KMF,CF' => 'KMF(CF)',
	'CDF,FC' => 'CDF(FC)',
	'XAF,FCFA' => 'XAF(FCFA)',
	'XOF,CFA' => 'XOF(CFA)',
	'XPF,CFP' => 'XPF(CFP)',
	'XCD,EC$' => 'XCD(EC$)',
	'TTD,TT$' => 'TTD(TT$)',
	'JMD,J$' => 'JMD(J$)',
	'BBD,Bds$' => 'BBD(Bds$)',
	'BZD,BZ$' => 'BZD(BZ$)',
	'GYD,GY$' => 'GYD(GY$)',
	'SRD,SR$' => 'SRD(SR$)',
	'CLP,CL$' => 'CLP(CL$)',
	'PEN,S/' => 'PEN(S/)',
	'UYU,$U' => 'UYU($U)',
	'PYG,₲' => 'PYG(₲)',
	'BOB,Bs' => 'BOB(Bs)',
	'ARS,$' => 'ARS($)',
	'COP,CO$' => 'COP(CO$)',
	'VEF,Bs' => 'VEF(Bs)',
	'UAH,₴' => 'UAH(₴)',
	'BYN,Br' => 'BYN(Br)',
	'MDL,L' => 'MDL(L)',
	'GEL,₾' => 'GEL(₾)',
	'AZN,₼' => 'AZN(₼)',
	'AMD,֏' => 'AMD(֏)',
	'GIP,£' => 'GIP(£)',
	'FJD,FJ$' => 'FJD(FJ$)',
	'PGK,K' => 'PGK(K)',
	'SBD,SI$' => 'SBD(SI$)',
	'VUV,VT' => 'VUV(VT)',
	'TOP,T$' => 'TOP(T$)',
	'WST,WS$' => 'WST(WS$)',
	'KID,$' => 'KID($)',
	'TVD,$' => 'TVD($)',
);

// Method to get available currencies as array
public function getAvailableCurrencies() {
    return $this->currency_symbols;
}

// Method to get multiple currencies as array
public function getMultipleCurrencies() {
    if (!empty($this->multiple_currencies)) {
        // The format is "CODE,SYMBOL,CODE,SYMBOL" so we need to split every 2nd comma
        $parts = explode(',', trim($this->multiple_currencies));
        $currencies = array();
        
        // Group by pairs (code,symbol)
        for ($i = 0; $i < count($parts); $i += 2) {
            if (isset($parts[$i]) && isset($parts[$i + 1])) {
                $currency = trim($parts[$i]) . ',' . trim($parts[$i + 1]);
                if (!empty($currency)) {
                    $currencies[] = $currency;
                }
            }
        }
        
        return $currencies;
    }
    return array();
}

// Method to check if a currency is enabled
public function isCurrencyEnabled($currency) {
    $enabledCurrencies = $this->getMultipleCurrencies();
    return in_array($currency, $enabledCurrencies);
}

}
?>