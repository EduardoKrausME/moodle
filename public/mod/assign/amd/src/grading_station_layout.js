// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

define(['core_user/repository', 'core/notification'], function(UserRepository, Notification) {
    'use strict';

    const preference = 'assign_grading_station_layout';
    const mediaQuery = '(max-width: 767px)';
    const minSplit = 8;
    const maxSplit = 92;

    const clampSplit = value => Math.max(minSplit, Math.min(maxSplit, Math.round(value)));
    const isArrowKey = key => ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(key);

    class GradingStationLayout {
        constructor() {
            this.root = document.querySelector('[data-region="grade-actions"]');
            this.review = document.querySelector('[data-region="review-panel"]');
            this.grade = document.querySelector('[data-region="grade-panel"]');
            this.reviewContent = this.review ? this.review.querySelector('[data-region="review-panel-content"]') : null;
            this.resizer = document.querySelector('[data-region="resizer"]');
            this.tabs = document.querySelector('[data-region="grading-mobile-tabs"]');
            this.mobile = window.matchMedia(mediaQuery);
            this.activeTab = 'grade';
            this.restoring = false;
            this.pointerId = null;
            this.mouseDragStarted = false;
            this.saveQueue = Promise.resolve();
            this.mode = this.root ? this.root.dataset.layoutMode : 'split';
            if (!['split', 'grade', 'review'].includes(this.mode)) {
                this.mode = 'split';
            }
            const split = this.root ? Number(this.root.dataset.layoutSplit) : 70;
            this.split = Number.isFinite(split) && split >= minSplit && split <= maxSplit ?
                clampSplit(split) : 70;
        }

        init() {
            if (!this.root || !this.review || !this.grade || !this.resizer || !this.tabs) {
                return;
            }
            this.reviewTab = this.tabs.querySelector('[data-region="grading-mobile-tab-review"]');
            this.gradeTab = this.tabs.querySelector('[data-region="grading-mobile-tab-grade"]');
            if (!this.reviewTab || !this.gradeTab) {
                return;
            }
            this.bindTabs();
            this.bindDesktopControls();
            this.bindResize();
            if (this.mobile.addEventListener) {
                this.mobile.addEventListener('change', () => this.sync());
            } else {
                this.mobile.addListener(() => this.sync());
            }
            this.sync();
        }

        bindTabs() {
            [['review', this.reviewTab], ['grade', this.gradeTab]].forEach(([name, button]) => {
                button.addEventListener('click', () => this.selectMobileTab(name));
                button.addEventListener('keydown', event => {
                    const keys = ['ArrowLeft', 'ArrowRight', 'Home', 'End'];
                    if (!keys.includes(event.key)) {
                        return;
                    }
                    event.preventDefault();
                    const next = event.key === 'Home' ? 'review' :
                        event.key === 'End' ? 'grade' :
                        this.activeTab === 'grade' ? 'review' : 'grade';
                    this.selectMobileTab(next);
                    (next === 'review' ? this.reviewTab : this.gradeTab).focus();
                });
            });
        }

        bindDesktopControls() {
            [
                ['grade', '.collapse-review-panel'],
                ['review', '.collapse-grade-panel'],
                ['split', '.collapse-none'],
            ].forEach(([mode, selector]) => {
                const button = this.root.querySelector(selector);
                if (!button) {
                    return;
                }
                const changed = () => {
                    if (this.restoring || this.mobile.matches) {
                        return;
                    }
                    this.mode = mode;
                    if (mode === 'split') {
                        this.applySplit();
                    }
                    this.updateDesktopAria();
                    this.persist();
                };
                button.addEventListener('click', changed);
                button.addEventListener('keydown', event => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        changed();
                    }
                });
            });
        }

        bindResize() {
            this.resizer.addEventListener('keyup', event => {
                if (!this.mobile.matches && isArrowKey(event.key)) {
                    this.captureNativeResize();
                }
            });
            this.resizer.addEventListener('mousedown', event => {
                if (event.button === 0 && !this.mobile.matches) {
                    this.mouseDragStarted = true;
                }
            });
            document.addEventListener('mouseup', event => {
                if (this.mouseDragStarted && event.button === 0 && !this.mobile.matches) {
                    this.mouseDragStarted = false;
                    this.captureNativeResize();
                }
            });
            this.resizer.addEventListener('pointerdown', event => {
                if (event.pointerType === 'mouse' || this.mobile.matches) {
                    return;
                }
                this.pointerId = event.pointerId;
                this.resizer.setPointerCapture(event.pointerId);
                event.preventDefault();
            });
            this.resizer.addEventListener('pointermove', event => {
                if (this.pointerId !== event.pointerId || this.mobile.matches) {
                    return;
                }
                const width = document.documentElement.clientWidth;
                if (!width) {
                    return;
                }
                let split = 100 * event.clientX / width;
                if (document.documentElement.dir === 'rtl') {
                    split = 100 - split;
                }
                this.split = clampSplit(split);
                this.mode = 'split';
                this.applySplit();
            });
            const stopPointer = event => {
                if (this.pointerId !== event.pointerId) {
                    return;
                }
                this.pointerId = null;
                if (this.resizer.hasPointerCapture(event.pointerId)) {
                    this.resizer.releasePointerCapture(event.pointerId);
                }
                if (event.type === 'pointerup') {
                    this.updateDesktopAria();
                    this.persist();
                }
            };
            this.resizer.addEventListener('pointerup', stopPointer);
            this.resizer.addEventListener('pointercancel', stopPointer);
        }

        captureNativeResize() {
            const reviewOnly = this.root.querySelector('.collapse-grade-panel.active');
            const gradeOnly = this.root.querySelector('.collapse-review-panel.active');
            if (gradeOnly) {
                this.mode = 'grade';
            } else if (reviewOnly) {
                this.mode = 'review';
            } else {
                const split = parseFloat(
                    getComputedStyle(document.documentElement).getPropertyValue('--mod-assign-panel-split')
                );
                if (!Number.isFinite(split) || split < minSplit || split > maxSplit) {
                    return;
                }
                this.mode = 'split';
                this.split = clampSplit(split);
                this.resizer.setAttribute('aria-valuenow', String(this.split));
            }
            this.updateDesktopAria();
            this.persist();
        }

        applySplit() {
            document.documentElement.style.setProperty('--mod-assign-panel-split', this.split + '%');
            this.resizer.setAttribute('aria-valuenow', String(this.split));
        }

        persist() {
            const value = this.mode + '_' + this.split;
            this.saveQueue = this.saveQueue.then(() => UserRepository.setUserPreference(preference, value))
                .catch(Notification.exception);
        }

        selectMobileTab(name) {
            if (!this.mobile.matches) {
                return;
            }
            this.activeTab = name;
            this.updateMobile();
            window.requestAnimationFrame(() => window.dispatchEvent(new Event('resize')));
        }

        updateMobile() {
            const reviewActive = this.activeTab === 'review';
            [[this.reviewTab, reviewActive], [this.gradeTab, !reviewActive]].forEach(([button, active]) => {
                button.setAttribute('aria-selected', String(active));
                button.tabIndex = active ? 0 : -1;
            });
            [[this.review, reviewActive, this.reviewTab], [this.grade, !reviewActive, this.gradeTab]]
                .forEach(([panel, active, button]) => {
                    if (!active && panel.contains(document.activeElement)) {
                        (reviewActive ? this.reviewTab : this.gradeTab).focus();
                    }
                    panel.setAttribute('role', 'tabpanel');
                    panel.setAttribute('aria-labelledby', button.id);
                    panel.setAttribute('tabindex', '0');
                    panel.setAttribute('aria-hidden', String(!active));
                });
            // The native preview controller also hides its inner region when
            // the desktop preview is collapsed. Restore it for mobile tabs.
            if (this.reviewContent) {
                this.reviewContent.setAttribute('aria-hidden', String(!reviewActive));
            }
        }

        updateDesktopAria() {
            this.review.removeAttribute('role');
            this.review.removeAttribute('aria-labelledby');
            this.grade.removeAttribute('role');
            this.grade.removeAttribute('aria-labelledby');
            this.review.removeAttribute('tabindex');
            this.grade.removeAttribute('tabindex');
            this.review.setAttribute('aria-hidden', String(this.mode === 'grade'));
            this.grade.setAttribute('aria-hidden', String(this.mode === 'review'));
            if (this.reviewContent) {
                this.reviewContent.setAttribute('aria-hidden', String(this.mode === 'grade'));
            }
            [
                ['grade', '.collapse-review-panel'],
                ['review', '.collapse-grade-panel'],
                ['split', '.collapse-none'],
            ].forEach(([mode, selector]) => {
                const button = this.root.querySelector(selector);
                if (button) {
                    button.setAttribute('aria-pressed', String(mode === this.mode));
                }
            });
        }

        sync() {
            if (this.mobile.matches) {
                document.body.classList.add('assign-grading-mobile-ready');
                this.updateMobile();
                return;
            }
            document.body.classList.remove('assign-grading-mobile-ready');
            this.restoring = true;
            const button = this.root.querySelector(
                this.mode === 'grade' ? '.collapse-review-panel' :
                this.mode === 'review' ? '.collapse-grade-panel' : '.collapse-none'
            );
            if (button) {
                button.click();
            }
            if (this.mode === 'split') {
                this.applySplit();
            }
            this.updateDesktopAria();
            this.restoring = false;
        }
    }

    return {
        init: () => new GradingStationLayout().init(),
    };
});
