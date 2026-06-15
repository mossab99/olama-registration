# تقرير مراجعة كود: Olama Registration Plugin
## تحليل نقاط الضعف والتحسين الممكنة

---

## 1. نقاط الضعف الأمنية (Critical → High)

### 1.1. Missing Authorization Checks on AJAX Endpoints (Severity: CRITICAL)
- **الملف:** `admin/class-reg-ajax.php`  
- **الموقع:** `guard()` method (خط 101-128)  
- **الوصف:** الدالة `guard()` تحتوي على قائمة طويلة من `current_user_can()` مربوطة بـ `&&` (AND). أي أن المستخدم يجب أن يمتلك **كل** الصلاحيات المذكورة في القائمة للوصول. هذه القائمة تضم صلاحيات من مجالات مختلفة (families, students, fees, invoices, payments, agreements, cheques...). المستخدم الذي يملك `manage_options` أو `olama_manage_registration_families` **لن** يمر إلا إذا كان يملك كل الصلاحيات الأخرى.  
- **الخطورة:** بعض Endpoints مثل `ajax_save_financial_row` و `ajax_create_invoice` قد تكون معطلة أو قد تمنع المستخدمين المصرح لهم فعلاً.  
- **الإصلاح المقترح:** تغيير العملية من `&&` إلى `||` مع تجميع الصلاحيات حسب المجال، أو استخدام `Olama_Reg_Payment_Policy::current_user_can_any()` على نمط ما يُستخدم في `ajax_record_payment` و `ajax_reverse_payment`.

### 1.2. Missing Capability Checks on GET-based Admin Actions (Severity: HIGH)
- **الملف:** `olama-registration.php` (خط 136-155)  
- **الموقع:** `force_db_create` و `olama_db_cleanup`  
- **الوصف:** يتم تنفيذ `run_migrations()` أو إعادة احتساب الفواتير عند استدعاء `?force_db_create=1` أو `?olama_db_cleanup=1` دون أي تحقق من `current_user_can('manage_options')`.  
- **الخطورة:** أي مستخدم يملك `edit_posts` أو أقل يمكنه استدعاء هذه الإجراءات وتعديل قاعدة البيانات.  
- **الإصلاح المقترح:** إضافة `if ( ! current_user_can( 'manage_options' ) ) { wp_die(); }` قبل تنفيذ أي عملية.

### 1.3. Missing Nonce / Capability on `olama_reg_deactivate` (Severity: HIGH)
- **الملف:** `admin/class-reg-ajax.php`  
- **الموقع:** `guard()` يتم استدعاؤه أولاً، لكن بعض الأوامر مثل `olama_reg_soft_delete_family` قد تمر إذا كان المستخدم يملك أي صلاحية واحدة في القائمة الطويلة.  
- **الوصف:** عملية "Soft Delete" للعائلة (`olama_reg_soft_delete_family`) تستخدم `ajax` method بدون تحقق إضافي من صلاحية محددة.  
- **الإصلاح المقترح:** إضافة `current_user_can( 'olama_manage_registration_families' )` داخل الـ method نفسه بعد `guard()`.

### 1.4. Potential SQL Injection via `$_GET`/`$_POST` in Admin (Severity: MEDIUM)
- **الملف:** `admin/class-reg-admin.php`  
- **الموقع:** `handle_print_actions()` (خط 385-479)  
- **الوصف:** يتم استخدام `$_GET['id']` و `$_GET['action']` في `wp_redirect` دون `sanitize_url` أو `esc_url`. الخطر منخفض لكنه موجود في `redirect_to` parameter.  
- **الملف:** `includes/class-reg-activator.php`  
- **الموقع:** خط 145-150: `json_decode` و `sanitize_text_field` على `children_names` مع تمرير مباشر إلى `insert` دون `prepare`.  
- **الإصلاح المقترح:** استخدام `prepare` بشكل أكثر صرامة، وتعقيم `$_GET` قبل استخدامه في `wp_redirect`.

### 1.5. Missing File Upload Validation & MIME Type Check (Severity: MEDIUM)
- **الملف:** `admin/class-reg-ajax.php` (خط 480-503)  
- **الموقع:** `ajax_upload_photo()`  
- **الوصف:** يتم استخدام `media_handle_upload('photo', 0)` بدون تحقق من نوع الملف أو حجمه. يمكن رفع ملفات ضارة إذا تم تخطي `wp_check_filetype_and_ext`.  
- **الإصلاح المقترح:** إضافة `wp_check_filetype_and_ext` و `getimagesize()` أو `wp_check_filetype()` قبل `media_handle_upload`.

### 1.6. No Rate Limiting on AJAX Endpoints (Severity: MEDIUM)
- **الملف:** `admin/class-reg-ajax.php`  
- **الوصف:** Endpoints مثل `ajax_search` و `ajax_record_payment` و `ajax_create_invoice` يمكن استدعاؤها بشكل متكرر دون أي Rate Limiting.  
- **الإصلاح المقترح:** إضافة `wp_nonce_field` (موجود بالفعل لكن `check_ajax_referer` لا يقي من تكرار الاستدعاء) أو استخدام `transient` لحساب عدد الاستدعاءات.

---

## 2. نقاط الضعف التقنية والهندسية (High → Medium)

### 2.1. Version Mismatch (Severity: HIGH)
- **الملف:** `olama-registration.php` (خط 6 و 17)  
- **الوصف:** `Version: 1.1.3` في header comment لكن `define('OLAMA_REG_VERSION', '1.3.2')` في الكود.  
- **الخطورة:** قد يسبب مشاكل في الكاش أو في تحديثات WordPress أو في `version_compare` للـ migrations.  
- **الإصلاح:** توحيد الرقم في `const` واحد أو `get_file_data()`.

### 2.2. Dual Data Model (Family + External Customer) — Code Complexity (Severity: HIGH)
- **الملفات:** `includes/class-reg-family.php`, `includes/class-reg-customer.php`, `includes/class-reg-child.php`  
- **الوصف:** النظام يدعم "عائلة داخلية" (family) و"عميل خارجي" (customer). هذا يتسبب في تكرار كبير في الكود (مثلاً في `ajax_get_family_billing()`، `get_customer_invoices()`، `generate_receipt_data()`). في كل مكان يتم فحص `if (strpos($family_uid, 'CUST-') === 0)` أو fallback بين `olama_families` و `olama_customers`.  
- **الخطورة:** صعوبة في الصيانة، احتمالية أخطاء منطقية، وصعوبة في إضافة ميزات جديدة.  
- **الإصلاح:** استخدام `Repository Pattern` أو `Unified Interface` (مثلاً `Olama_Reg_Payer_Interface`) لتوحيد الوصول للعائلة والعميل.

### 2.3. No Error Handling / Rollback in Activator (Severity: MEDIUM)
- **الملف:** `includes/class-reg-activator.php` (خط 14-42)  
- **الوصف:** `activate()` و `run_migrations()` لا تستخدم `try/catch` أو `transaction`. إذا فشل `dbDelta` في منتصف الطريق، قد تكون الجداول ناقصة.  
- **الإصلاح:** لف `run_migrations` في `try/catch` أو `transaction` وإعادة رفع الخطأ.

### 2.4. Missing Dependency Check for `Olama_School_DB` (Severity: MEDIUM)
- **الملف:** `olama-registration.php` (خط 29-37)  
- **الوصف:** يتم فحص `class_exists('Olama_School_DB')` لكن لا يتم فحص `Olama_School_Academic` أو `Olama_School_Helpers` اللذين يُستخدمان في عدة ملفات (`class-reg-ajax.php`، `class-reg-financial.php`).  
- **الإصلاح:** إضافة فحوصات إضافية أو `function_exists`/`class_exists` guards في كل مكان يُستخدم فيه.

### 2.5. Use of `$_SERVER['REMOTE_ADDR']` directly (Severity: LOW)
- **الملفات:** `includes/class-reg-billing-invoice.php`، `includes/class-reg-billing-payment.php`، `includes/class-reg-cash-session.php`  
- **الوصف:** يتم استخدام `$_SERVER['REMOTE_ADDR']` مباشرة في `ip_address`. في بيئات `reverse proxy` أو `load balancer`، قد تكون القيمة خاطئة.  
- **الإصلاح:** استخدام `wp_get_environment_type()` أو `$_SERVER['HTTP_X_FORWARDED_FOR']` مع تعقيم.

### 2.6. Missing `UNIQUE` on `olama_reg_financial` (Severity: LOW)
- **الملف:** `includes/class-reg-activator.php` (خط 491-520)  
- **الوصف:** الجدول `olama_reg_financial` لا يحتوي على `UNIQUE KEY` لمنع تكرار الأسطر لنفس العائلة والسنة.  
- **الإصلاح:** إضافة `UNIQUE KEY` إذا كان منطقياً، أو حظر الإدخال المكرر في `save_row()`.

### 2.7. No `REST API` Documentation / OpenAPI (Severity: LOW)
- **الوصف:** الـ plugin يستخدم AJAX endpoints تقليدية (`wp_ajax_`) بدون أي `REST API` أو `schema`.  
- **الإصلاح:** ترحيل تدريجي إلى `register_rest_route` مع `schema` و `permission_callback`.

---

## 3. نقاط التحسين المقترحة (Performance & UX)

### 3.1. Performance — Database Queries
- **الملف:** `includes/class-reg-agreement.php` (خط 134-203)  
- **الوصف:** `get_list()` تقوم بتشغيل استعلام `SELECT *` ثم loop على كل صف لتنفيذ 2-3 استعلامات إضافية لكل صف (`resolve_payer_name`, `resolve_participant_name`). إذا كانت النتائج 100 صف، يتم تنفيذ 200-300 استعلام إضافي (N+1 problem).  
- **التحسين:** استخدام `JOIN` مع `olama_customers` و `olama_families` و `olama_students` في الاستعلام الرئيسي، أو استخدام `IN` مع `get_results()` مرة واحدة.

### 3.2. Performance — CSS/JS Assets
- **الملف:** `admin/class-reg-admin.php` (خط 276-380)  
- **الوصف:** يتم تحميل `select2` من CDN و `jquery-ui-theme` من CDN على كل صفحة admin للـ plugin. إذا كان المستخدم يعمل في بيئة intranet أو offline، ستفشل التحميلات.  
- **التحسين:** تضمين نسخ محلية أو استخدام `wp_enqueue_script` مع `fallback`.

### 3.3. Performance — Invoice Recalculation
- **الملف:** `includes/class-reg-billing-invoice.php` (خط 577-636)  
- **الوصف:** `recalculate_totals()` يتم استدعاؤها في كل `create`/`update`/`cancel`/`payment`. إذا كانت الفاتورة تحتوي على 100 بند، يتم تنفيذ 4-5 استعلامات SQL في كل مرة.  
- **التحسين:** استخدام `UPDATE ... JOIN` أو `SQL aggregate` في استعلام واحد بدلاً من `get_var` متعدد.

### 3.4. UX — JavaScript Code Size
- **الملف:** `assets/js/olama-reg.js` (3810 سطر)  
- **الوصف:** الملف يحتوي على 3800+ سطر من jQuery بدون أي modularization أو tree-shaking. يتم تحميله على كل صفحة admin.  
- **التحسين:** تقسيم الملف إلى `modules` (invoice.js, payment.js, family.js, agreement.js) وتحميل كل module فقط عند الحاجة (`wp_enqueue_script` conditional).

### 3.5. UX — Missing Form Validation in JS
- **الملف:** `assets/js/olama-reg.js` (خط 99-131)  
- **الوصف:** `saveFamily` يجمع كل `input` بدون validation على `required` أو `email` أو `phone`. يتم الاعتماد على Server-side validation فقط.  
- **التحسين:** إضافة `HTML5 validation` أو `JS validation` قبل إرسال الـ AJAX.

### 3.6. UX — No Keyboard Shortcuts / Accessibility
- **الملف:** `assets/js/olama-reg.js`  
- **الوصف:** لا يوجد `aria-label` كافٍ، `tabindex` منظمة، أو `keyboard shortcuts` (مثل Ctrl+S للحفظ).  
- **التحسين:** إضافة `aria-*` attributes و `keyboard event listeners`.

### 3.7. UX — Print Views Lack Print CSS Optimization
- **الملف:** `admin/views/partial-print-card.php`، `admin/views/html-agreements-print.php`  
- **الوصف:** الـ print views تحتوي على `media="print"` CSS لكن بعضها يستخدم `px` units بدون `mm` أو `cm` (قياسات الطباعة القياسية).  
- **التحسين:** استخدام `@media print` مع `page-break-inside: avoid` و `size: A4`.

### 3.8. UX — Missing Bulk Actions on Tables
- **الملفات:** `admin/class-reg-family-table.php`، `admin/class-reg-student-table.php`  
- **الوصف:** الـ `WP_List_Table` المستخدم لا يدعم `bulk actions` (حذف جماعي، تفعيل/تعطيل).  
- **التحسين:** تفعيل `get_bulk_actions()` و `process_bulk_action()` في الـ tables.

---

## 4. نقاط التحسين المعمارية (Architecture)

### 4.1. No Dependency Injection (DI) Container
- **الوصف:** كل الـ classes تعتمد على `global $wpdb` مباشرة. هذا يجعل الـ Unit Testing صعباً.  
- **التحسين:** استخدام `constructor injection` أو `static factory` لتمرير `$wpdb` أو `mock` للـ testing.

### 4.2. No Unit Tests / PHPUnit
- **الملف:** لا يوجد `tests/` directory  
- **التحسين:** إضافة `phpunit.xml` و `tests/` directory مع `bootstrap` لـ WordPress testing framework.

### 4.3. No Logging / Error Tracking ( beyond `error_log` )
- **الملف:** `olama-registration.php` (خط 121)  
- **الوصف:** يتم استخدام `error_log()` فقط في `activation hook`.  
- **التحسين:** استخدام `Monolog` أو `WP Logger` مع `different levels` (debug, info, warning, error).

### 4.4. No API Versioning
- **الوصف:** الـ AJAX endpoints لا تحتوي على `v1/` أو `v2/`. أي تغيير في الـ response format يؤدي إلى كسر الـ frontend.  
- **التحسين:** إضافة `version` parameter أو `accept header` في الـ AJAX.

### 4.5. Hardcoded Arabic Text
- **الملفات:** `assets/js/olama-reg.js`، `admin/views/*.php`  
- **الوصف:** بعض النصوص العربية مكتوبة مباشرة في JS (مثل `alert('يجب أن يحتوي نموذج الرسوم على بند واحد على الأقل.')`) بدون `wp_localize_script` أو `olamaReg.strings`.  
- **التحسين:** نقل كل النصوص إلى `wp_localize_script` أو `__()` في PHP.

---

## 5. ملخص الأولويات

| الأولوية | النقطة | الملف | السرعة |
|----------|--------|-------|--------|
| 🔴 Critical | تغيير `&&` إلى `||` في `guard()` | `admin/class-reg-ajax.php` | فوري |
| 🔴 Critical | إضافة `current_user_can` على `force_db_create` | `olama-registration.php` | فوري |
| 🟠 High | توحيد رقم الإصدار | `olama-registration.php` | سريع |
| 🟠 High | حل N+1 في `get_list()` | `includes/class-reg-agreement.php` | متوسط |
| 🟡 Medium | تقسيم `olama-reg.js` إلى modules | `assets/js/olama-reg.js` | متوسط |
| 🟡 Medium | إضافة `try/catch` في Activator | `includes/class-reg-activator.php` | سريع |
| 🟢 Low | إضافة `UNIQUE KEY` على `olama_reg_financial` | `includes/class-reg-activator.php` | سريع |
| 🟢 Low | إضافة `aria-label` و `keyboard shortcuts` | `assets/js/olama-reg.js` | متوسط |

---

**الخلاصة:** الـ plugin يعمل بشكل جيد ويحتوي على بنية تحتية قوية (مثل `dbDelta`, `audit trail`, `cash sessions`, `payment allocations`). لكنه يحتاج إلى:
1. **إصلاح عاجل** في التحقق من الصلاحيات (security).
2. **توحيد** رقم الإصدار ونموذج البيانات.
3. **تحسين الأداء** في الاستعلامات المتكررة (N+1) وتقسيم JS.
4. **إضافة اختبارات** ونظام logging لتسهيل الصيانة المستقبلية.
