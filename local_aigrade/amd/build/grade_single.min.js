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
 * AMD module for AI Grade single submission button and feedback side panel
 *
 * @module     local_aigrade/grade_single
 * @copyright  2025 Brian A. Pool, National Trail Local Schools
 * @copyright  2026 Anpassung Hochschulkontext FH Graubünden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {

    return {
        /**
         * Initialize the grade single button and feedback panel.
         * @param {string} buttonUrl The URL for the AJAX request
         * @param {string} buttonText The text to display on the button
         * @param {string} sesskey The session key
         * @param {Object} strings Localised UI strings
         */
        init: function(buttonUrl, buttonText, sesskey, strings) {

            strings = strings || {};

            // Tracks the feedback HTML currently shown in the panel, to avoid
            // needless re-rendering (which would reset scroll position).
            var lastRendered = null;

            var getUserId = function() {
                var urlParams = new URLSearchParams(window.location.search);
                return urlParams.get('userid') || '0';
            };

            // Read the current submission's feedback content from the native
            // "Feedback comments" editor. This is the single source of truth:
            // the panel always mirrors whatever belongs to the displayed
            // submission, so switching submissions never shows stale text.
            var readFeedbackContent = function() {
                // 1) Live TinyMCE editor (Moodle 4.x default).
                try {
                    if (window.tinyMCE && window.tinyMCE.editors) {
                        var eds = window.tinyMCE.editors;
                        for (var i = 0; i < eds.length; i++) {
                            if (eds[i] && eds[i].id && eds[i].id.indexOf('assignfeedbackcomments') > -1) {
                                return eds[i].getContent();
                            }
                        }
                    }
                } catch (e) {
                    // Ignore and fall through to the DOM-based fallbacks.
                }
                // 2) Underlying textarea (server-rendered value on load, Atto sync).
                var ta = document.querySelector('textarea[name="assignfeedbackcomments_editor[text]"]');
                if (ta && typeof ta.value === 'string') {
                    return ta.value;
                }
                // 3) Generic contenteditable fallback.
                var ce = document.querySelector('[id*="assignfeedbackcomments"] [contenteditable="true"]');
                if (ce) {
                    return ce.innerHTML;
                }
                return '';
            };

            // Treat markup that carries no visible text (e.g. "<p>&nbsp;</p>") as empty.
            var isEmptyContent = function(html) {
                if (!html) {
                    return true;
                }
                var text = html.replace(/<[^>]*>/g, '').replace(/&nbsp;/gi, ' ').trim();
                return text.length === 0;
            };

            // Whether the user is currently editing the feedback field; if so we
            // do not touch the panel, to avoid disturbing their editing.
            var feedbackHasFocus = function() {
                var ae = document.activeElement;
                if (!ae) {
                    return false;
                }
                if (ae.tagName === 'IFRAME' && ae.id && ae.id.indexOf('assignfeedbackcomments') > -1) {
                    return true;
                }
                if (ae.getAttribute && ae.getAttribute('name') === 'assignfeedbackcomments_editor[text]') {
                    return true;
                }
                if (ae.closest && ae.closest('[id*="assignfeedbackcomments"]')) {
                    return true;
                }
                return false;
            };

            // Scroll the native feedback comments field into view (for editing/saving).
            var jumpToFeedbackField = function() {
                var target = document.querySelector(
                    '[id^="fitem_id_assignfeedbackcomments"], [id*="assignfeedbackcomments_editor"]'
                );
                if (target && target.scrollIntoView) {
                    target.scrollIntoView({behavior: 'smooth', block: 'center'});
                }
            };

            var buildPanel = function() {
                var panel = document.createElement('div');
                panel.id = 'aigrade-feedback-panel';
                panel.className = 'aigrade-feedback-panel';

                var header = document.createElement('div');
                header.className = 'aigrade-feedback-panel-header';

                var title = document.createElement('span');
                title.className = 'aigrade-feedback-panel-title';
                title.textContent = strings.paneltitle || 'KI-Feedback';

                var actions = document.createElement('span');
                actions.className = 'aigrade-feedback-panel-actions';

                var jump = document.createElement('button');
                jump.type = 'button';
                jump.className = 'btn btn-link btn-sm aigrade-feedback-panel-jump';
                jump.textContent = strings.paneljump || 'Zum Feedback-Feld';
                jump.addEventListener('click', function(e) {
                    e.preventDefault();
                    jumpToFeedbackField();
                });

                var toggle = document.createElement('button');
                toggle.type = 'button';
                toggle.className = 'btn btn-sm aigrade-feedback-panel-toggle';
                toggle.setAttribute('aria-label', strings.panelcollapse || 'Ein-/ausklappen');
                toggle.textContent = '⟩';
                toggle.addEventListener('click', function() {
                    panel.classList.toggle('aigrade-collapsed');
                    toggle.textContent = panel.classList.contains('aigrade-collapsed') ? '⟨' : '⟩';
                });

                actions.appendChild(jump);
                actions.appendChild(toggle);
                header.appendChild(title);
                header.appendChild(actions);

                var body = document.createElement('div');
                body.className = 'aigrade-feedback-panel-body';

                panel.appendChild(header);
                panel.appendChild(body);
                document.body.appendChild(panel);
                return panel;
            };

            // Show the panel with the given HTML (creating it on first use).
            var showPanel = function(html) {
                var panel = document.getElementById('aigrade-feedback-panel') || buildPanel();
                panel.querySelector('.aigrade-feedback-panel-body').innerHTML = html;
                panel.style.display = '';
            };

            var hidePanel = function() {
                var panel = document.getElementById('aigrade-feedback-panel');
                if (panel) {
                    panel.style.display = 'none';
                    panel.querySelector('.aigrade-feedback-panel-body').innerHTML = '';
                }
            };

            // Keep the panel in sync with the current submission's feedback field.
            var syncPanel = function() {
                // Never disturb the user while they edit the feedback field.
                if (feedbackHasFocus()) {
                    return;
                }
                var html = readFeedbackContent();
                if (isEmptyContent(html)) {
                    if (lastRendered !== '') {
                        hidePanel();
                        lastRendered = '';
                    }
                    return;
                }
                if (html !== lastRendered) {
                    showPanel(html);
                    lastRendered = html;
                }
            };

            var insertButton = function() {
                // Don't insert if button already exists
                if ($('.aigrade-single-button').length > 0) {
                    return true;
                }

                // Create the button
                var button = $('<button>')
                    .attr('type', 'button')
                    .addClass('btn btn-primary aigrade-single-button')
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
                                    // The feedback has been saved to the comment field
                                    // server-side; reload so the field (and thus the
                                    // synced panel) reflect the saved content.
                                    window.location.reload();
                                } else if (response.already_graded) {
                                    // Feedback already exists for this submission.
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
                    .addClass('local_aigrade-button-container')
                    .append(button);

                // Try multiple insertion points in order of preference
                var inserted = false;

                // Option 1: After grade input row (preferred - puts it right after the grade field)
                var gradeInput = $('input[name="grade"]');
                if (gradeInput.length) {
                    var gradeRow = gradeInput.closest('.row, .fitem');
                    if (gradeRow.length) {
                        gradeRow.after(container);
                        inserted = true;
                    }
                }

                // Option 2: Before feedback section (if grade input not found)
                if (!inserted) {
                    var feedbackSection = $('div[id*="fitem_id_assignfeedbackcomments"]');
                    if (feedbackSection.length) {
                        feedbackSection.before(container);
                        inserted = true;
                    }
                }

                // Option 3: Inside grade panel
                if (!inserted) {
                    var gradePanel = $('[data-region="grade-panel"]');
                    if (gradePanel.length) {
                        gradePanel.prepend(container);
                        inserted = true;
                    }
                }

                // Option 4: Fallback to main content area
                if (!inserted) {
                    var mainContent = $('#region-main-box, #region-main, [role="main"]').first();
                    if (mainContent.length) {
                        mainContent.prepend(container);
                        inserted = true;
                    }
                }

                return inserted;
            };

            // Initial insertion + sync.
            setTimeout(insertButton, 100);
            setTimeout(syncPanel, 400);

            $(document).ready(function() {
                setTimeout(insertButton, 100);
                setTimeout(syncPanel, 400);
            });

            $(window).on('load', function() {
                setTimeout(insertButton, 200);
                setTimeout(syncPanel, 500);
            });

            // Re-insert the button when the grader swaps content dynamically.
            var observer = new MutationObserver(function() {
                insertButton();
            });
            observer.observe(document.body, {childList: true, subtree: true});

            $(document).ajaxComplete(function() {
                setTimeout(insertButton, 100);
                setTimeout(syncPanel, 200);
            });

            // Continuously keep the panel mirrored to the current submission's
            // feedback field (covers AJAX navigation between submissions, where
            // no full page reload happens).
            setInterval(syncPanel, 1000);

            // Button insertion fallback for the first few seconds.
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
