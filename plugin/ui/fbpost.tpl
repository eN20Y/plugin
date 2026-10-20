{include file="sections/header.tpl"}

<div class="row">
    <div class="col-md-12">
        {if !$fbPostAllowed}
            <div class="alert alert-danger" role="alert">Only a SuperAdmin or Admin can post to the Facebook Page.</div>
        {else}
        <div class="panel panel-primary panel-hovered panel-stacked mb30">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-sm-8">
                        <strong>Facebook Page Post</strong>
                        <div class="small">Publish a post, link or photo to the same Facebook Page as your Messenger bot.</div>
                    </div>
                    <div class="col-sm-4 text-right">
                        {if $fbPostHasToken}
                            <form method="post" action="{$_url}plugin/fbpost" style="display:inline-block;">
                                <input type="hidden" name="csrf_token" value="{$fbPostCsrf|escape}">
                                <input type="hidden" name="action" value="test">
                                <button type="submit" class="btn btn-default btn-sm">
                                    <i class="ion ion-checkmark-circled"></i> Test connection
                                </button>
                            </form>
                        {/if}
                    </div>
                </div>
            </div>
            <div class="panel-body">
                {if $fbPostNotice}
                    <div class="alert alert-success" role="alert">{$fbPostNotice|escape}</div>
                {/if}
                {if $fbPostError}
                    <div class="alert alert-danger" role="alert">{$fbPostError|escape}</div>
                {/if}

                {if !$fbPostHasToken}
                    <div class="alert alert-warning" role="alert">
                        No Page Access Token is saved yet. Add it in the Messenger Bot Settings (gear icon on Payment Verification), then come back.
                    </div>
                {else}
                    <div class="alert alert-info">
                        Posts go to the Page connected to your Messenger bot token. The token also needs the
                        <strong>pages_manage_posts</strong> and <strong>pages_read_engagement</strong> permissions in Meta, otherwise Facebook will reject the post.
                    </div>
                {/if}

                <form id="fbpost-form" method="post" enctype="multipart/form-data" action="{$_url}plugin/fbpost" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="{$fbPostCsrf|escape}">
                    <input type="hidden" name="action" value="post">

                    <div class="form-group" style="max-width:420px;">
                        <label>Use a template (optional)</label>
                        <select class="form-control" id="fbpost-template">
                            <option value="">- Choose a template -</option>
                            {foreach $fbPostTemplates as $k => $t}
                                <option value="{$k|escape}">{$t.label|escape}</option>
                            {/foreach}
                        </select>
                        <small class="help-block">Fills in the message and the banner text. Edit anything before posting.</small>
                    </div>

                    <div id="fbpost-fill-box" style="display:none; margin-bottom:15px;">
                        <label>Fill in the details</label>
                        <div id="fbpost-fill-fields" class="row"></div>
                        <small class="help-block">Type each detail once. It replaces the matching [placeholder] in the message and in the banner. Typing here rebuilds those texts, so make any wording changes after you finish filling these in.</small>
                    </div>

                    <div class="form-group">
                        <label>Message</label>
                        <textarea class="form-control" id="fbpost-message" name="message" rows="10" maxlength="5000"
                                  placeholder="What do you want to post?">{$fbPostForm.message|escape}</textarea>
                        <div id="fbpost-ph" class="text-danger small" style="margin-top:4px;"></div>
                    </div>

                    <div class="form-group">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" id="fbpost-banner-on"> Attach a banner image with the notice title
                            </label>
                        </div>
                        <small class="help-block">Makes a picture from the text below and posts it as a photo, with your message as the caption. It replaces any photo chosen further down.</small>
                    </div>

                    <div id="fbpost-banner-box" style="display:none; margin-bottom:15px;">
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label>Banner title</label>
                                    <input type="text" class="form-control" id="fbpost-banner-headline" maxlength="80">
                                </div>
                                <div class="form-group">
                                    <label>Banner details (date, time, area)</label>
                                    <input type="text" class="form-control" id="fbpost-banner-details" maxlength="120">
                                </div>
                                <div class="form-group">
                                    <label>Banner footer</label>
                                    <input type="text" class="form-control" id="fbpost-banner-footer" maxlength="100">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label>Preview</label>
                                <canvas id="fbpost-banner-canvas" width="1200" height="630"
                                        style="width:100%; max-width:480px; height:auto; border:1px solid #ddd; border-radius:4px; display:block;"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Link (optional)</label>
                                <input type="url" class="form-control" name="link" maxlength="500"
                                       value="{$fbPostForm.link|escape}" placeholder="https://">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Photo (optional)</label>
                                <input type="file" name="photo" accept="image/jpeg,image/png,image/gif">
                                <small class="help-block">JPG, PNG or GIF, up to 4 MB. A photo post cannot include a link.</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="max-width:320px;">
                        <label>Schedule (optional)</label>
                        <input type="datetime-local" class="form-control" name="schedule_at"
                               value="{$fbPostForm.schedule_at|escape}">
                        <small class="help-block">Leave empty to post now. From 10 minutes to 30 days ahead.</small>
                    </div>

                    <button type="submit" class="btn btn-success" {if !$fbPostHasToken}disabled{/if}>
                        <i class="ion ion-paper-airplane"></i> Post to Page
                    </button>
                </form>

                <hr>
                <h4>Recent posts</h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Posted</th>
                                <th>Type</th>
                                <th>Message</th>
                                <th>Scheduled for</th>
                                <th>By</th>
                                <th>Facebook ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $fbPostList as $p}
                                <tr>
                                    <td>{$p.created_at|escape}</td>
                                    <td>{$p.kind|escape}</td>
                                    <td>
                                        {if $p.message}{$p.message|truncate:120:'...'|escape}{/if}
                                        {if $p.link}<br><small>{$p.link|escape}</small>{/if}
                                    </td>
                                    <td>{if $p.scheduled_for}{$p.scheduled_for|escape}{else}-{/if}</td>
                                    <td>{$p.created_by|escape}</td>
                                    <td><small>{$p.fb_post_id|escape}</small></td>
                                </tr>
                            {foreachelse}
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No posts yet</td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>var FB_POST_TEMPLATES = {$fbPostTemplatesJson nofilter};</script>
{literal}
        <script>
        (function () {
            var T = window.FB_POST_TEMPLATES || {};
            var form = document.getElementById('fbpost-form');
            if (!form) { return; }

            var sel = document.getElementById('fbpost-template');
            var msg = document.getElementById('fbpost-message');
            var ph = document.getElementById('fbpost-ph');
            var useBanner = document.getElementById('fbpost-banner-on');
            var box = document.getElementById('fbpost-banner-box');
            var hEl = document.getElementById('fbpost-banner-headline');
            var dEl = document.getElementById('fbpost-banner-details');
            var fEl = document.getElementById('fbpost-banner-footer');
            var canvas = document.getElementById('fbpost-banner-canvas');
            var photo = form.querySelector('input[name=photo]');
            var link = form.querySelector('input[name=link]');
            var btn = form.querySelector('button[type=submit]');
            var current = '';
            var lastKey = '';
            var base = null;
            var fillBox = document.getElementById('fbpost-fill-box');
            var fillFields = document.getElementById('fbpost-fill-fields');

            function roundRect(ctx, x, y, w, h, r) {
                ctx.beginPath();
                ctx.moveTo(x + r, y);
                ctx.arcTo(x + w, y, x + w, y + h, r);
                ctx.arcTo(x + w, y + h, x, y + h, r);
                ctx.arcTo(x, y + h, x, y, r);
                ctx.arcTo(x, y, x + w, y, r);
                ctx.closePath();
            }

            function wrap(ctx, text, maxW) {
                var words = String(text).split(/\s+/);
                var lines = [];
                var line = '';
                words.forEach(function (w) {
                    var test = line ? line + ' ' + w : w;
                    if (ctx.measureText(test).width > maxW && line) {
                        lines.push(line);
                        line = w;
                    } else {
                        line = test;
                    }
                });
                if (line) { lines.push(line); }
                return lines;
            }

            function draw() {
                var W = 1200, H = 630;
                var ctx = canvas.getContext('2d');
                canvas.width = W;
                canvas.height = H;

                var t = (current && T[current]) ? T[current] : null;
                var c1 = t ? t.color1 : '#334155';
                var c2 = t ? t.color2 : '#0f172a';
                var icon = t ? t.icon : '\uD83D\uDCE2';

                var g = ctx.createLinearGradient(0, 0, W, H);
                g.addColorStop(0, c1);
                g.addColorStop(1, c2);
                ctx.fillStyle = g;
                ctx.fillRect(0, 0, W, H);

                ctx.fillStyle = 'rgba(255,255,255,0.08)';
                ctx.beginPath(); ctx.arc(1090, 80, 230, 0, Math.PI * 2); ctx.fill();
                ctx.beginPath(); ctx.arc(110, 610, 190, 0, Math.PI * 2); ctx.fill();

                ctx.fillStyle = 'rgba(255,255,255,0.14)';
                roundRect(ctx, 60, 60, 1080, 510, 32);
                ctx.fill();

                ctx.textBaseline = 'alphabetic';
                ctx.fillStyle = '#ffffff';
                ctx.font = '100px "Segoe UI Emoji","Apple Color Emoji","Noto Color Emoji",sans-serif';
                ctx.fillText(icon, 110, 175);

                var headline = (hEl.value || '').trim();
                var size = 84;
                var lines = [];
                for (size = 84; size >= 44; size -= 4) {
                    ctx.font = 'bold ' + size + 'px Arial, Helvetica, sans-serif';
                    lines = wrap(ctx, headline, 960);
                    if (lines.length <= 2) { break; }
                }
                ctx.fillStyle = '#ffffff';
                var y = 265;
                lines.forEach(function (ln) {
                    ctx.fillText(ln, 110, y);
                    y += Math.round(size * 1.12);
                });

                var details = (dEl.value || '').trim();
                if (details) {
                    ctx.font = 'bold 40px Arial, Helvetica, sans-serif';
                    ctx.fillStyle = 'rgba(255,255,255,0.95)';
                    y += 20;
                    wrap(ctx, details, 960).slice(0, 2).forEach(function (ln) {
                        ctx.fillText(ln, 110, y);
                        y += 52;
                    });
                }

                var footer = (fEl.value || '').trim();
                if (footer) {
                    ctx.font = '30px Arial, Helvetica, sans-serif';
                    ctx.fillStyle = 'rgba(255,255,255,0.85)';
                    ctx.fillText(footer, 110, 535, 960);
                }
            }

            function holders() {
                var s = msg.value;
                if (useBanner.checked) {
                    s += ' ' + hEl.value + ' ' + dEl.value + ' ' + fEl.value;
                }
                var m = s.match(/\[[^\]\n]+\]/g) || [];
                return m.filter(function (v, i) { return m.indexOf(v) === i; });
            }

            function refresh() {
                var h = holders();
                ph.textContent = h.length ? 'Still to fill in: ' + h.join(', ') : '';
                if (useBanner.checked) { draw(); }
            }

            // One input per distinct [PLACEHOLDER] found in the chosen template.
            function buildFields() {
                fillFields.innerHTML = '';
                var src = [base.message, base.headline, base.details, base.footer].join(' ');
                var m = src.match(/\[[^\]\n]+\]/g) || [];
                var uniq = m.filter(function (v, i) { return m.indexOf(v) === i; });

                uniq.forEach(function (p) {
                    var col = document.createElement('div');
                    col.className = 'col-sm-4';
                    var grp = document.createElement('div');
                    grp.className = 'form-group';
                    var lab = document.createElement('label');
                    lab.textContent = p.slice(1, -1).toLowerCase().replace(/(^|\s)\S/g, function (c) { return c.toUpperCase(); });
                    var inp = document.createElement('input');
                    inp.type = 'text';
                    inp.className = 'form-control';
                    inp.maxLength = 150;
                    inp.placeholder = p;
                    inp.setAttribute('data-ph', p);
                    inp.addEventListener('input', applyFill);
                    grp.appendChild(lab);
                    grp.appendChild(inp);
                    col.appendChild(grp);
                    fillFields.appendChild(col);
                });

                fillBox.style.display = uniq.length ? 'block' : 'none';
            }

            // Rebuild the message and banner texts from the template, using the typed details.
            function applyFill() {
                if (!base) { return; }
                var inputs = Array.prototype.slice.call(fillFields.querySelectorAll('input[data-ph]'));
                function sub(s) {
                    inputs.forEach(function (i) {
                        var v = i.value.trim();
                        if (v) { s = s.split(i.getAttribute('data-ph')).join(v); }
                    });
                    return s;
                }
                msg.value = sub(base.message);
                hEl.value = sub(base.headline);
                dEl.value = sub(base.details);
                fEl.value = sub(base.footer);
                refresh();
            }

            sel.addEventListener('change', function () {
                var key = sel.value;
                if (key === '' || !T[key]) { lastKey = ''; return; }
                if (msg.value.trim() !== '' && !confirm('Replace the text you already typed with this template?')) {
                    sel.value = lastKey;
                    return;
                }
                var t = T[key];
                current = key;
                lastKey = key;
                msg.value = t.message || '';
                hEl.value = t.headline || '';
                dEl.value = t.details || '';
                fEl.value = t.footer || '';
                base = {
                    message: t.message || '',
                    headline: t.headline || '',
                    details: t.details || '',
                    footer: t.footer || ''
                };
                buildFields();
                useBanner.checked = true;
                box.style.display = 'block';
                refresh();
            });

            useBanner.addEventListener('change', function () {
                box.style.display = useBanner.checked ? 'block' : 'none';
                refresh();
            });

            [msg, hEl, dEl, fEl].forEach(function (el) {
                el.addEventListener('input', refresh);
            });

            form.addEventListener('submit', function (e) {
                e.preventDefault();

                var left = holders();
                if (left.length) {
                    alert('Replace these placeholders before posting: ' + left.join(', '));
                    return;
                }
                if (useBanner.checked && link.value.trim() !== '') {
                    alert('A photo post cannot include a link. Clear the link or turn off the banner image.');
                    return;
                }
                if (useBanner.checked && (hEl.value || '').trim() === '') {
                    alert('Enter a banner title, or turn off the banner image.');
                    return;
                }
                if (!confirm('Publish this to your Facebook Page?')) { return; }

                if (!useBanner.checked) {
                    btn.disabled = true;
                    form.submit();
                    return;
                }

                if (!canvas.toBlob || !window.DataTransfer || !window.File) {
                    alert('This browser cannot attach the banner image. Turn it off, or use an up-to-date browser.');
                    return;
                }

                btn.disabled = true;
                draw();
                canvas.toBlob(function (blob) {
                    try {
                        if (!blob) { throw new Error('no image'); }
                        var file = new File([blob], 'notice.png', { type: 'image/png' });
                        var dt = new DataTransfer();
                        dt.items.add(file);
                        photo.files = dt.files;
                    } catch (err) {
                        btn.disabled = false;
                        alert('Could not create the banner image. Turn it off, or use an up-to-date browser.');
                        return;
                    }
                    form.submit();
                }, 'image/png');
            });
        })();
        </script>
{/literal}
        {/if}
    </div>
</div>

{include file="sections/footer.tpl"}
