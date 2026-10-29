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

/**
 * AMD module for AI Grade single submission button and feedback side panel.
 *
 * The AI feedback is stored in the plugin's own table (advisory only) and shown
 * read-only in a side panel; it is NOT written into Moodle's feedback comment
 * field. Teachers can adopt it explicitly via the "apply" button.
 *
 * @module     local_assignmentaifeedback/grade_single
 * @copyright  2025 Brian A. Pool, National Trail Local Schools
 * @copyright  2026 Anpassung Hochschulkontext FH Graubünden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {

    return {
        /**
         * @param {string} buttonUrl   AJAX URL for grading
         * @param {string} buttonText  Button label
         * @param {string} sesskey     Session key
         * @param {Object} strings     Localised UI strings
         * @param {string} feedbackUrl AJAX URL to fetch stored feedback
         * @param {boolean} showButton Whether to offer the manual grading button. False when
         *                             the assignment grades automatically on submission; the
         *                             panel below is still shown, it displays the result.
         */
        init: function(buttonUrl, buttonText, sesskey, strings, feedbackUrl, showButton) {

            strings = strings || {};
            showButton = (showButton !== false);

            // The feedback currently shown in the panel (for the "apply" button).
            var currentFeedbackHtml = '';
            // The userid the panel currently reflects, to detect submission switches.
            var loadedUserid = null;

            var getUserId = function() {
                var urlParams = new URLSearchParams(window.location.search);
                return urlParams.get('userid') || '0';
            };

            var isEmptyContent = function(html) {
                if (!html) {
                    return true;
                }
                return html.replace(/<[^>]*>/g, '').replace(/&nbsp;/gi, ' ').trim().length === 0;
            };

            // Locate the feedback comments textarea (server-rendered element the
            // editor is attached to).
            var getFeedbackTextarea = function() {
                return document.querySelector('textarea[name="assignfeedbackcomments_editor[text]"]')
                    || document.querySelector('textarea[id*="assignfeedbackcomments"]');
            };

            // Locate the live TinyMCE editor instance for the feedback field, if any.
            // Moodle 4.x exposes TinyMCE as window.tinymce (lowercase); older builds
            // used window.tinyMCE. We resolve the instance via the textarea id first,
            // then fall back to matching by id substring.
            var getTinyEditor = function() {
                var tiny = window.tinymce || window.tinyMCE;
                if (!tiny) {
                    return null;
                }
                try {
                    var ta = getFeedbackTextarea();
                    if (ta && ta.id && typeof tiny.get === 'function') {
                        var byId = tiny.get(ta.id);
                        if (byId) {
                            return byId;
                        }
                    }
                    var eds = tiny.editors || [];
                    for (var i = 0; i < eds.length; i++) {
                        if (eds[i] && eds[i].id && eds[i].id.indexOf('assignfeedbackcomments') > -1) {
                            return eds[i];
                        }
                    }
                } catch (e) {
                    return null;
                }
                return null;
            };

            // Read the current content of Moodle's feedback comments editor.
            var readFeedbackContent = function() {
                var ed = getTinyEditor();
                if (ed) {
                    try {
                        return ed.getContent();
                    } catch (e) {
                        // Fall through to DOM.
                    }
                }
                var ta = getFeedbackTextarea();
                if (ta && typeof ta.value === 'string') {
                    return ta.value;
                }
                var ce = document.querySelector('[id*="assignfeedbackcomments"] [contenteditable="true"]');
                return ce ? ce.innerHTML : '';
            };

            var jumpToFeedbackField = function() {
                var target = document.querySelector(
                    '[id^="fitem_id_assignfeedbackcomments"], [id*="assignfeedbackcomments_editor"]'
                );
                if (target && target.scrollIntoView) {
                    target.scrollIntoView({behavior: 'smooth', block: 'center'});
                }
            };

            // Copy the AI feedback into Moodle's feedback comments field on demand.
            var applyToFeedbackField = function() {
                if (!currentFeedbackHtml) {
                    return;
                }
                if (!isEmptyContent(readFeedbackContent())) {
                    if (!confirm(strings.applyconfirm ||
                            'The feedback field already contains text. Overwrite it with the AI feedback?')) {
                        return;
                    }
                }
                var handled = false;

                // 1) Live TinyMCE editor (Moodle 4.x default).
                var ed = getTinyEditor();
                if (ed) {
                    try {
                        ed.setContent(currentFeedbackHtml);
                        if (typeof ed.save === 'function') {
                            ed.save(); // Sync into the underlying textarea.
                        }
                        handled = true;
                    } catch (e) {
                        handled = false;
                    }
                }

                // 2) No TinyMCE: set the Atto contenteditable area (if any) and the
                //    underlying textarea, and notify listeners so Moodle registers the change.
                if (!handled) {
                    var ce = document.querySelector('[id*="assignfeedbackcomments"] [contenteditable="true"]');
                    if (ce) {
                        ce.innerHTML = currentFeedbackHtml;
                        ce.dispatchEvent(new Event('input', {bubbles: true}));
                        handled = true;
                    }
                    var ta = getFeedbackTextarea();
                    if (ta) {
                        ta.value = currentFeedbackHtml;
                        ta.dispatchEvent(new Event('input', {bubbles: true}));
                        ta.dispatchEvent(new Event('change', {bubbles: true}));
                        handled = true;
                    }
                }

                if (!handled) {
                    alert(strings.applyfailed || 'Feedback field not found.');
                    return;
                }

                jumpToFeedbackField();
            };

            // Panel position and size chosen by the user, kept per browser across
            // submissions and page loads. Null while the panel is docked on the right.
            var LAYOUT_KEY = 'local_assignmentaifeedback_panel_layout';

            var loadLayout = function() {
                try {
                    var layout = JSON.parse(window.localStorage.getItem(LAYOUT_KEY));
                    return (layout && typeof layout.left === 'number' && typeof layout.top === 'number') ? layout : null;
                } catch (e) {
                    return null;
                }
            };

            var saveLayout = function(layout) {
                try {
                    if (layout) {
                        window.localStorage.setItem(LAYOUT_KEY, JSON.stringify(layout));
                    } else {
                        window.localStorage.removeItem(LAYOUT_KEY);
                    }
                } catch (e) {
                    // Storage unavailable (private mode, blocked): position is just not remembered.
                }
            };

            // Keep the whole panel on screen, so the header and the resize handle stay
            // reachable. Its height is capped by max-height, so it always fits.
            var moveTo = function(panel, left, top) {
                var rect = panel.getBoundingClientRect();
                // clientWidth/Height exclude the scrollbars, unlike window.innerWidth/Height.
                var viewport = document.documentElement;
                left = Math.max(0, Math.min(left, viewport.clientWidth - rect.width));
                top = Math.max(0, Math.min(top, viewport.clientHeight - rect.height));
                panel.style.left = left + 'px';
                panel.style.top = top + 'px';
            };

            // Place the panel: at the stored position (kept inside the viewport) or,
            // without one, docked to the right edge at the top defined in styles.css.
            var placePanel = function(panel) {
                if (panel.style.display === 'none') {
                    return;
                }
                var layout = loadLayout();
                // Set the border style first, it changes the width that is measured below.
                panel.classList.toggle('assignmentaifeedback-floating', !!layout);
                if (layout) {
                    moveTo(panel, layout.left, layout.top);
                    return;
                }
                // Measure from the left edge, so a collapsed (auto width) panel is not
                // squeezed by its previous position.
                panel.style.left = '0px';
                panel.style.top = '';
                var top = parseFloat(window.getComputedStyle(panel).top) || 0;
                moveTo(panel, document.documentElement.clientWidth - panel.getBoundingClientRect().width, top);
            };

            var rememberLayout = function(panel) {
                var rect = panel.getBoundingClientRect();
                var layout = {left: rect.left, top: rect.top};
                if (panel.style.width) {
                    layout.width = panel.style.width;
                }
                if (panel.style.height) {
                    layout.height = panel.style.height;
                }
                saveLayout(layout);
                panel.classList.add('assignmentaifeedback-floating');
            };

            // Drag the panel by its header bar; double-click resets it to the docked default.
            var makeMovable = function(panel, header) {
                var drag = null;

                header.addEventListener('pointerdown', function(e) {
                    if (e.button !== 0 || e.target.closest('button')) {
                        return;
                    }
                    var rect = panel.getBoundingClientRect();
                    drag = {dx: e.clientX - rect.left, dy: e.clientY - rect.top, moved: false};
                    header.setPointerCapture(e.pointerId);
                    e.preventDefault();
                });

                header.addEventListener('pointermove', function(e) {
                    if (!drag) {
                        return;
                    }
                    moveTo(panel, e.clientX - drag.dx, e.clientY - drag.dy);
                    if (!drag.moved) {
                        drag.moved = true;
                        panel.classList.add('assignmentaifeedback-dragging');
                    }
                });

                var endDrag = function() {
                    if (drag && drag.moved) {
                        rememberLayout(panel);
                    }
                    drag = null;
                    panel.classList.remove('assignmentaifeedback-dragging');
                };
                header.addEventListener('pointerup', endDrag);
                header.addEventListener('pointercancel', endDrag);

                header.addEventListener('dblclick', function(e) {
                    if (e.target.closest('button')) {
                        return;
                    }
                    saveLayout(null);
                    panel.style.width = '';
                    panel.style.height = '';
                    placePanel(panel);
                });

                // A size change via the resize handle (inline width/height) is remembered
                // together with the position. Content changes alone leave no inline size.
                if (window.ResizeObserver) {
                    new window.ResizeObserver(function() {
                        if (!drag && (panel.style.width || panel.style.height) &&
                                panel.style.display !== 'none' &&
                                !panel.classList.contains('assignmentaifeedback-collapsed')) {
                            rememberLayout(panel);
                        }
                    }).observe(panel);
                }

                window.addEventListener('resize', function() {
                    placePanel(panel);
                });
            };

            var buildPanel = function() {
                var panel = document.createElement('div');
                panel.id = 'assignmentaifeedback-feedback-panel';
                panel.className = 'assignmentaifeedback-feedback-panel';

                var header = document.createElement('div');
                header.className = 'assignmentaifeedback-feedback-panel-header';
                header.title = strings.panelmove || 'Ziehen zum Verschieben, Doppelklick setzt Position und Grösse zurück';

                var title = document.createElement('span');
                title.className = 'assignmentaifeedback-feedback-panel-title';
                title.textContent = strings.paneltitle || 'KI-Feedback';

                var toggle = document.createElement('button');
                toggle.type = 'button';
                toggle.className = 'btn btn-sm assignmentaifeedback-feedback-panel-toggle';
                toggle.setAttribute('aria-label', strings.panelcollapse || 'Ein-/ausklappen');
                toggle.textContent = '⟩';
                toggle.addEventListener('click', function() {
                    panel.classList.toggle('assignmentaifeedback-collapsed');
                    toggle.textContent = panel.classList.contains('assignmentaifeedback-collapsed') ? '⟨' : '⟩';
                    // Docked: stay flush right; moved: stay where it is, inside the viewport.
                    placePanel(panel);
                });

                header.appendChild(title);
                header.appendChild(toggle);

                // Toolbar with the actions (kept out of the header so the long
                // "apply" label does not overflow the narrow panel).
                var toolbar = document.createElement('div');
                toolbar.className = 'assignmentaifeedback-feedback-panel-toolbar';

                var apply = document.createElement('button');
                apply.type = 'button';
                apply.className = 'btn btn-secondary btn-sm assignmentaifeedback-feedback-panel-apply';
                apply.textContent = strings.apply || 'Ins Feedback-Feld übernehmen';
                apply.addEventListener('click', function(e) {
                    e.preventDefault();
                    applyToFeedbackField();
                });

                var jump = document.createElement('button');
                jump.type = 'button';
                jump.className = 'btn btn-link btn-sm assignmentaifeedback-feedback-panel-jump';
                jump.textContent = strings.paneljump || 'Zum Feedback-Feld';
                jump.addEventListener('click', function(e) {
                    e.preventDefault();
                    jumpToFeedbackField();
                });

                toolbar.appendChild(apply);
                toolbar.appendChild(jump);

                var body = document.createElement('div');
                body.className = 'assignmentaifeedback-feedback-panel-body';

                panel.appendChild(header);
                panel.appendChild(toolbar);
                panel.appendChild(body);

                var layout = loadLayout();
                if (layout) {
                    panel.style.width = layout.width || '';
                    panel.style.height = layout.height || '';
                }
                document.body.appendChild(panel);
                makeMovable(panel, header);
                return panel;
            };

            var showPanel = function(html) {
                var panel = document.getElementById('assignmentaifeedback-feedback-panel') || buildPanel();
                panel.querySelector('.assignmentaifeedback-feedback-panel-body').innerHTML = html;
                panel.style.display = '';
                placePanel(panel);
            };

            var hidePanel = function() {
                var panel = document.getElementById('assignmentaifeedback-feedback-panel');
                if (panel) {
                    panel.style.display = 'none';
                    panel.querySelector('.assignmentaifeedback-feedback-panel-body').innerHTML = '';
                }
            };

            // Load the stored AI feedback for a given submission and show/hide the panel.
            var loadFeedbackFor = function(userid) {
                loadedUserid = userid;
                if (!feedbackUrl) {
                    return;
                }
                $.ajax({
                    url: feedbackUrl + '&userid=' + userid + '&sesskey=' + sesskey,
                    method: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        // Ignore stale responses if the user navigated on in the meantime.
                        if (userid !== getUserId()) {
                            return;
                        }
                        if (response && response.success && response.feedback && !isEmptyContent(response.feedback)) {
                            currentFeedbackHtml = response.feedback;
                            showPanel(response.feedback);
                        } else {
                            currentFeedbackHtml = '';
                            hidePanel();
                        }
                    },
                    error: function() {
                        currentFeedbackHtml = '';
                        hidePanel();
                    }
                });
            };

            // Re-load the panel whenever the displayed submission changes.
            var syncToCurrentSubmission = function() {
                var uid = getUserId();
                if (uid !== loadedUserid) {
                    loadFeedbackFor(uid);
                }
            };

            // How long a queued run may take is governed by the cron interval, so
            // poll rather than hold the request open. Bounded, so the button never
            // stays disabled forever.
            var POLL_INTERVAL_MS = 3000;
            var POLL_MAX_MS = 15 * 60 * 1000;

            // Wait for the suggestion a queued grading run produces. 'since' is the
            // timemodified of the feedback that was on screen when the run was
            // queued, so a regrade is not mistaken for the old suggestion.
            var pollForFeedback = function(userid, since, btn, originalText) {
                var started = Date.now();

                var finish = function() {
                    btn.prop('disabled', false).text(originalText)
                        .data('force-regrade', false)
                        .removeClass('btn-warning').addClass('btn-primary');
                };

                var tick = function() {
                    // The teacher moved on to another submission: stop polling.
                    if (userid !== getUserId()) {
                        finish();
                        return;
                    }

                    $.ajax({
                        url: feedbackUrl + '&userid=' + userid + '&sesskey=' + sesskey,
                        method: 'GET',
                        dataType: 'json',
                        complete: function(xhr) {
                            var response = xhr.responseJSON;

                            if (response && response.success && response.timemodified > since &&
                                    !isEmptyContent(response.feedback)) {
                                currentFeedbackHtml = response.feedback;
                                loadedUserid = userid;
                                showPanel(response.feedback);
                                finish();
                                return;
                            }

                            if (Date.now() - started > POLL_MAX_MS) {
                                finish();
                                alert(strings.pendingtimeout ||
                                    'The AI suggestion is still being generated. It will appear here once ready.');
                                return;
                            }

                            setTimeout(tick, POLL_INTERVAL_MS);
                        }
                    });
                };

                setTimeout(tick, POLL_INTERVAL_MS);
            };

            var insertButton = function() {
                // Nothing to insert when the assignment grades automatically on submission.
                // Reported as "done" so the retry interval below stops straight away.
                if (!showButton) {
                    return true;
                }

                if ($('.assignmentaifeedback-single-button').length > 0) {
                    return true;
                }

                var button = $('<button>')
                    .attr('type', 'button')
                    .addClass('btn btn-primary assignmentaifeedback-single-button')
                    .text(buttonText)
                    .data('force-regrade', false)
                    .on('click', function() {
                        var btn = $(this);
                        var originalText = btn.text();
                        var forceRegrade = btn.data('force-regrade');

                        btn.prop('disabled', true).text(strings.inprogress || 'Grading...');

                        var currentUserid = getUserId();

                        var url = buttonUrl.replace('userid=0', 'userid=' + currentUserid) +
                            '&action=grade&sesskey=' + sesskey;
                        if (forceRegrade) {
                            url += '&force_regrade=1';
                        }

                        $.ajax({
                            url: url,
                            method: 'POST',
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    // The AI request runs in a background task; wait for the
                                    // suggestion to show up instead of blocking the request.
                                    if (response.queued) {
                                        btn.text(strings.queued || 'Queued...');
                                        pollForFeedback(currentUserid, response.since || 0, btn, originalText);
                                        return;
                                    }
                                    // Show the new suggestion directly in the panel; nothing
                                    // is written to the feedback field (advisory only).
                                    if (response.feedback) {
                                        currentFeedbackHtml = response.feedback;
                                        loadedUserid = currentUserid;
                                        showPanel(response.feedback);
                                    }
                                    btn.prop('disabled', false).text(originalText);
                                    btn.data('force-regrade', false)
                                        .removeClass('btn-warning').addClass('btn-primary');
                                } else if (response.already_graded) {
                                    btn.prop('disabled', false);
                                    btn.text(strings.regrade || 'Regrade with AI');
                                    btn.removeClass('btn-primary').addClass('btn-warning');
                                    btn.data('force-regrade', true);

                                    if (confirm(strings.confirmregrade ||
                                            'This submission already has feedback. Do you want to regenerate it with AI?')) {
                                        btn.click();
                                    }
                                } else {
                                    var errorMsg = response.error || strings.errorunknown || 'Unknown error occurred';
                                    alert((strings.errorprefix || 'Error: ') + errorMsg);
                                    btn.prop('disabled', false).text(originalText);
                                }
                            },
                            error: function(xhr, status, error) {
                                alert((strings.errorserver || 'Error communicating with server: ') + error);
                                btn.prop('disabled', false).text(originalText);
                            }
                        });
                    });

                var container = $('<div>')
                    .addClass('local_assignmentaifeedback-button-container')
                    .append(button);

                var inserted = false;

                var gradeInput = $('input[name="grade"]');
                if (gradeInput.length) {
                    var gradeRow = gradeInput.closest('.row, .fitem');
                    if (gradeRow.length) {
                        gradeRow.after(container);
                        inserted = true;
                    }
                }

                if (!inserted) {
                    var feedbackSection = $('div[id*="fitem_id_assignfeedbackcomments"]');
                    if (feedbackSection.length) {
                        feedbackSection.before(container);
                        inserted = true;
                    }
                }

                if (!inserted) {
                    var gradePanel = $('[data-region="grade-panel"]');
                    if (gradePanel.length) {
                        gradePanel.prepend(container);
                        inserted = true;
                    }
                }

                if (!inserted) {
                    var mainContent = $('#region-main-box, #region-main, [role="main"]').first();
                    if (mainContent.length) {
                        mainContent.prepend(container);
                        inserted = true;
                    }
                }

                return inserted;
            };

            setTimeout(insertButton, 100);
            setTimeout(syncToCurrentSubmission, 400);

            $(document).ready(function() {
                setTimeout(insertButton, 100);
                setTimeout(syncToCurrentSubmission, 400);
            });

            $(window).on('load', function() {
                setTimeout(insertButton, 200);
                setTimeout(syncToCurrentSubmission, 500);
            });

            var observer = new MutationObserver(function() {
                insertButton();
            });
            observer.observe(document.body, {childList: true, subtree: true});

            $(document).ajaxComplete(function() {
                setTimeout(insertButton, 100);
                setTimeout(syncToCurrentSubmission, 200);
            });

            // Detect submission switches (also when navigation does not reload the page).
            setInterval(syncToCurrentSubmission, 1000);

            var checkCount = 0;
            var checkInterval = setInterval(function() {
                checkCount++;
                if (insertButton() || checkCount >= 20) {
                    clearInterval(checkInterval);
                }
            }, 500);
        }
    };
});
