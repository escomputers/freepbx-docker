<!-- Toolbar -->
<div id="logfiles_toolbar" class="toolbar">
    <!-- Row First -->
    <div class="row">
        <!-- Block Files -->
        <div class="col-lg-6">
            <div class="input-group">
                <span class="input-group-addon">
                    <?php echo _("Files"); ?> <span class="label label-default">(<span class="count_files_in_list">0</span>)</span>
                </span>
                <div class="input-group-btn">
                    <a class="btn btn-default btn-onlyico btn-reload-list" title="<?php echo _("Reload List"); ?>">
                        <i class="fa fa-refresh"></i>
                    </a>
                </div>
                <select class="form-control" name="filename_log" required="required" disabled></select>
                <div class="input-group-btn">
                    <a class="btn btn-default btn-onlyico btn-reload-file disabled" title="<?php echo _("Reload File"); ?>">
                        <i class="fa fa-chevron-circle-left"></i>
                    </a>
                    <a class="btn btn-danger btn-onlyico btn-file-delte disabled" title="<?php echo _("Delete File"); ?>">
                        <i class="fa fa-trash"></i>
                    </a>
                    <a class="btn btn-default btn-onlyico btn-file-export disabled" title="<?php echo _("Export File"); ?>">
                        <i class="fa fa-download"></i>
                    </a>
                </div>
            </div>
        </div>
        <!-- Block Files -->
        <!-- Block Lineas -->
        <div class="col-lg-2">
            <div class="input-group">
                <span class="input-group-addon"><?php echo _("Lines"); ?></span>
                <input type="number" class="form-control" name="lines_log" placeholder="Lines" min="1" value="">
            </div>
        </div>
        <!-- Block Lineas -->
        <!-- Block Refres Interval -->
        <div class="col-lg-3">
            <div class="input-group">
                <span class="input-group-addon"><?php echo _("Refresh Interval"); ?></span>
                <div class="input-group-btn">
                    <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="refresh-interval-time-now"><?php echo _("Disabled"); ?></span> <span class="caret"></span>
                        
                    </button>
                    <ul class="dropdown-menu dropdown-menu-right">
                        <li>
                            <input type="hidden" name="interval_time" value="0">
                            <a href="#" class="refresh-interval-item"><?php echo _("Disabled"); ?></a>
                        </li>
                        <li role="separator" class="divider"></li>
                        <?php
                            $ls_timers = [3, 5, 10, 15, 20, 30, 40, 50, 60];
                            foreach ($ls_timers as $timer)
                            {
                                echo sprintf('<li><input type="hidden" value="%1$d"><a href="#" class="refresh-interval-item">%1$d %2$s</a></li>', $timer, _('Seconds'));
                            }
                        ?>
                    </ul>
                </div>
            </div>
        </div>
        <!-- Block Refres Interval -->
         <!-- Block FullScreen -->
         <div class="col-lg-1">
            <button type="button" class="btn btn-default btn-onlyico btn-block btn-fullscreen" title="<?php echo _("Activate full screen mode. Press ESC to exit full screen mode."); ?>" disabled>
                <i class="fa fa-arrows-alt" aria-hidden="true"></i>
            </button>
        </div>
        <!-- Block FullScreen -->
    </div>
    <!-- Row One -->
    <!-- Row Two -->
    <div class="row">
        <!-- Block Filter -->
        <div class="col-lg-4">
            <div class="input-group">
                <span class="input-group-addon"><?php echo _("Filter"); ?></span>
                <input type="text" class="form-control" name="fileter_log" placeholder="Text to filter" spellcheck="false" disabled>
                <div class="input-group-btn">
                    <a class="btn btn-default btn-onlyico btn-filter-apply disabled" title="<?php echo _("Apply Filter"); ?>">
                        <i class="fa fa-filter"></i>
                    </a>
                    <a class="btn btn-danger btn-onlyico btn-filter-clean disabled" title="<?php echo _("Clean Filter"); ?>">
                        <i class="fa fa-eraser"></i>
                    </a>
                </div>
            </div>
        </div>
        <!-- Block Filter -->
        <!-- Block Highlight -->
        <div class="col-lg-4">
            <div class="input-group">
                <span class="input-group-addon">
                    <?php echo _("Highlight"); ?> <span class="label label-default"> (<span class="count_highlight">0</span>)</span>
                </span>
                <input type="text" class="form-control" name="highlight_log" placeholder="Text to Highlight" spellcheck="false" disabled>
                <div class="input-group-btn">
                    <a class="btn btn-primary btn-onlyico btn-highlight-apply disabled" title="<?php echo _("Apply Highlight"); ?>">
                        <i class="fa fa-filter"></i>
                    </a>
                    <a class="btn btn-danger btn-onlyico btn-highlight-clean disabled" title="<?php echo _("Clean Highlight"); ?>">
                        <i class="fa fa-eraser"></i>
                    </a>
                </div>
            </div>
        </div>
        <!-- Block Highlight -->
        <!-- Block Auto-Scroll -->
        <div class="col-lg-3">
            <div class="input-group box-autoscroll">
                <span class="input-group-addon"><?php echo _("Auto-Scroll"); ?></span>
                <div class="input-group-btn">
                    <div class="radioset">
                        <input type="radio" name="auto_scroll" id="auto_scroll_on" value="on">
                        <label for="auto_scroll_on"><i class="fa fa-check-circle" aria-hidden="true"></i></label>
                        <input type="radio" name="auto_scroll" id="auto_scroll_off" value="off">
                        <label for="auto_scroll_off"><i class="fa fa-times-circle" aria-hidden="true"></i></label>
                    </div>
                </div>
            </div>   
        </div>
        <!-- Block Auto-Scroll -->
        <!-- Block More Options -->
        <div class="col-lg-1 box-more-options">
            <button type="button" class="btn btn-default btn-block dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="<?php echo _("More Options"); ?>">
                <span>
                    <i class="fa fa-cogs" aria-hidden="true"></i>
                </span>
                <span class="caret"></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-right">
                <li>
                    <div class="input-group input-group-radioset">
                        <span class="input-group-addon"><?php echo _("Show Row Numbers"); ?></span>
                        <div class="input-group-btn">
                            <div class="radioset">
                                <input type="radio" name="show_col_num_line" id="show_col_num_line_on" value="on">
                                <label for="show_col_num_line_on"><i class="fa fa-check-circle" aria-hidden="true"></i></label>
                                <input type="radio" name="show_col_num_line" id="show_col_num_line_off" value="off">
                                <label for="show_col_num_line_off"><i class="fa fa-times-circle" aria-hidden="true"></i></label>
                            </div>
                        </div>
                    </div>
                </li>
                <li>
                    <div class="input-group input-group-radioset">
                        <span class="input-group-addon"><?php echo _("Show Line Spacing"); ?></span>
                        <div class="input-group-btn">
                            <div class="radioset">
                                <input type="radio" name="show_line_spacing" id="show_line_spacing_on" value="on">
                                <label for="show_line_spacing_on"><i class="fa fa-check-circle" aria-hidden="true"></i></label>
                                <input type="radio" name="show_line_spacing" id="show_line_spacing_off" value="off">
                                <label for="show_line_spacing_off"><i class="fa fa-times-circle" aria-hidden="true"></i></label>
                            </div>
                        </div>
                    </div>
                </li>
                <li>
                    <div class="input-group input-group-radioset">
                        <span class="input-group-addon"><?php echo _("Show Only Row's Highlight"); ?></span>
                        <div class="input-group-btn">
                            <div class="radioset">
                                <input type="radio" name="show_only_rows_highlight" id="show_only_rows_highlight_on" value="on">
                                <label for="show_only_rows_highlight_on"><i class="fa fa-check-circle" aria-hidden="true"></i></label>
                                <input type="radio" name="show_only_rows_highlight" id="show_only_rows_highlight_off" value="off">
                                <label for="show_only_rows_highlight_off"><i class="fa fa-times-circle" aria-hidden="true"></i></label>
                            </div>
                        </div>
                    </div>
                </li>
                <li >
                    <div class="input-group">
                        <span class="input-group-addon"><?php echo _("Buffer Size"); ?></span>
                        <input type="number" name="size_buffer" class="form-control" min="0" value="">
                    </div>
                </li>
            </ul>
        </div>
        <!-- Block More Options -->
    </div>
    <!-- Row Two -->
</div>
<!-- Toolbar -->