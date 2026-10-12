{{-- Shared workflow-help body. Included from both admin/automation/help.blade.php and user/automation/help.blade.php --}}
<div class="row g-4">
    <div class="col-md-4">
        <div class="card sticky-top" style="top:80px;">
            <div class="card-body">
                <h6 class="text-muted small mb-3 text-uppercase">{{ translate('On this page') }}</h6>
                <nav class="nav flex-column">
                    <a class="nav-link" href="#concepts">1. {{ translate('Core concepts') }}</a>
                    <a class="nav-link" href="#triggers">2. {{ translate('Triggers') }}</a>
                    <a class="nav-link" href="#actions">3. {{ translate('Actions') }}</a>
                    <a class="nav-link" href="#conditions">4. {{ translate('Conditions & Waits') }}</a>
                    <a class="nav-link" href="#variables">5. {{ translate('Personalization variables') }}</a>
                    <a class="nav-link" href="#error-handling">6. {{ translate('Error handling') }}</a>
                    <a class="nav-link" href="#testing">7. {{ translate('Test Run / Dry Run') }}</a>
                    <a class="nav-link" href="#debug">8. {{ translate('Debugging executions') }}</a>
                </nav>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card mb-3" id="concepts">
            <div class="card-body">
                <h4>1. {{ translate('Core concepts') }}</h4>
                <p>{{ translate('A workflow is a sequence of nodes that runs for each contact who matches its trigger. There are four node types:') }}</p>
                <ul>
                    <li><strong>{{ translate('Trigger') }}</strong> — {{ translate('the event that starts the workflow (e.g. a new contact, an incoming reply, a webhook).') }}</li>
                    <li><strong>{{ translate('Action') }}</strong> — {{ translate('something the workflow does (send SMS, send email, add a tag, call a webhook).') }}</li>
                    <li><strong>{{ translate('Condition') }}</strong> — {{ translate('branches the workflow based on contact data (has-tag, field-equals, A/B split).') }}</li>
                    <li><strong>{{ translate('Wait') }}</strong> — {{ translate('pauses the workflow for a duration or until a specific time.') }}</li>
                </ul>
            </div>
        </div>

        <div class="card mb-3" id="triggers">
            <div class="card-body">
                <h4>2. {{ translate('Triggers') }}</h4>
                <ul>
                    <li><code>new_contact</code> — {{ translate('A new contact is added to the (optionally specified) group.') }}</li>
                    <li><code>contact_replied</code> — {{ translate('A contact sends an incoming message on WhatsApp / SMS / email.') }}</li>
                    <li><code>no_response</code> — {{ translate("A contact hasn't replied within N days.") }}</li>
                    <li><code>webhook</code> — {{ translate('An external system POSTs to the unique workflow webhook URL.') }}</li>
                    <li><code>schedule</code> — {{ translate('Daily / weekly / monthly at a chosen time.') }}</li>
                    <li><code>birthday</code> — {{ translate("A contact's birthday — optionally N days before.") }}</li>
                    <li><code>manual</code> — {{ translate('You trigger it by hand from the workflow page.') }}</li>
                </ul>
            </div>
        </div>

        <div class="card mb-3" id="actions">
            <div class="card-body">
                <h4>3. {{ translate('Actions') }}</h4>
                <ul>
                    <li><strong>{{ translate('Send SMS / Email / WhatsApp') }}</strong> — {{ translate('Pick a gateway (or Android device for SMS) + write the message.') }}</li>
                    <li><strong>{{ translate('Add Tag') }}</strong>, <strong>{{ translate('Add to Group') }}</strong>, <strong>{{ translate('Remove from Group') }}</strong>, <strong>{{ translate('Update Contact') }}</strong> — {{ translate('Mutate contact data based on workflow progress.') }}</li>
                    <li><strong>{{ translate('Notify Admin') }}</strong> — {{ translate('Send a heads-up email or SMS to an admin address.') }}</li>
                    <li><strong>{{ translate('Call Webhook') }}</strong> — {{ translate('POST/PUT/PATCH/GET to an external URL with custom JSON headers + body. Contact and workflow details are auto-appended.') }}</li>
                    <li><strong>{{ translate('AI Auto Reply') }}</strong> — {{ translate("Use OpenAI (or DeepSeek / Groq / Together AI via custom base URL) to generate a contextual reply to an incoming message. Defaults to matching the customer's language.") }}</li>
                </ul>
            </div>
        </div>

        <div class="card mb-3" id="conditions">
            <div class="card-body">
                <h4>4. {{ translate('Conditions & Waits') }}</h4>
                <p><strong>{{ translate('Condition nodes') }}</strong> {{ translate('split the workflow into Yes/No branches based on the contact:') }}</p>
                <ul>
                    <li><code>has_tag</code>, <code>in_group</code>, <code>field_equals</code></li>
                    <li><code>random_split</code> ({{ translate('A/B testing — N% goes Yes, rest goes No') }})</li>
                    <li><code>day_of_week</code>, <code>time_between</code> ({{ translate('time-based branching') }})</li>
                </ul>
                <p><strong>{{ translate('Wait nodes') }}</strong> {{ translate('pause the workflow:') }}</p>
                <ul>
                    <li><code>delay</code> — N minutes / hours / days</li>
                    <li><code>until_time</code> — {{ translate('next occurrence of HH:MM') }}</li>
                    <li><code>until_date</code> — {{ translate('a specific date + time') }}</li>
                </ul>
            </div>
        </div>

        <div class="card mb-3" id="variables">
            <div class="card-body">
                <h4>5. {{ translate('Personalization variables') }}</h4>
                <p>{{ translate('Use these in any message body or subject. They get substituted at send time:') }}</p>
                <ul class="list-unstyled font-monospace small">
                    <li><code>&#123;&#123;first_name&#125;&#125;</code></li>
                    <li><code>&#123;&#123;last_name&#125;&#125;</code></li>
                    <li><code>&#123;&#123;email&#125;&#125;</code></li>
                    <li><code>&#123;&#123;phone&#125;&#125;</code></li>
                    <li><code>&#123;&#123;whatsapp&#125;&#125;</code></li>
                    <li><code>&#123;&#123;unsubscribe_link&#125;&#125;</code> — {{ translate('email only; auto-appended footer if you omit it') }}</li>
                </ul>
            </div>
        </div>

        <div class="card mb-3" id="error-handling">
            <div class="card-body">
                <h4>6. {{ translate('Error handling') }}</h4>
                <p>{{ translate('Each action node exposes an Error Handling section in its properties panel:') }}</p>
                <ul>
                    <li><strong>{{ translate('On failure') }}</strong>: <code>continue</code> (default), <code>stop</code>, <code>skip</code>.</li>
                    <li><strong>{{ translate('Retry attempts') }}</strong>: 0–10 additional tries before declaring the node failed.</li>
                    <li><strong>{{ translate('Retry delay') }}</strong>: seconds between retries.</li>
                </ul>
                <p>{{ translate('Retries use the queue worker — make sure a worker is running (cron schedule:run every minute is sufficient).') }}</p>
            </div>
        </div>

        <div class="card mb-3" id="testing">
            <div class="card-body">
                <h4>7. {{ translate('Test Run / Dry Run') }}</h4>
                <p>{{ translate('On the workflow edit page, click') }} <strong>{{ translate('Test Run') }}</strong> {{ translate('to simulate the whole workflow against a sample contact. No SMS, email, WhatsApp, or webhook calls are made — every node reports what it WOULD do. Use this to catch misconfigured gateways, missing fields, and dead branches before activating.') }}</p>
            </div>
        </div>

        <div class="card mb-3" id="debug">
            <div class="card-body">
                <h4>8. {{ translate('Debugging executions') }}</h4>
                <p>{{ translate('Open any workflow → Executions list → click the eye icon to view the per-node timeline:') }}</p>
                <ul>
                    <li>{{ translate('Each step shows ok / error / info status with a timestamp and elapsed milliseconds since the previous step.') }}</li>
                    <li>{{ translate('The Context Variables panel shows the resolved value of every variable at the time the execution was logged.') }}</li>
                    <li>{{ translate('If a node errored, the error message is shown in red along with the data the action attempted to send.') }}</li>
                </ul>
            </div>
        </div>
    </div>
</div>
