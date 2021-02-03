#!/bin/sh

time -v php -dopcache.jit_buffer_size=0 test_accumulate_inline_nonstrict.php
time -v php -dopcache.jit_buffer_size=0 test_accumulate_inline_strict.php
time -v php -dopcache.jit_buffer_size=0 test_accumulate_call_direct_nonstrict.php
time -v php -dopcache.jit_buffer_size=0 test_accumulate_call_direct_strict.php
time -v php -dopcache.jit_buffer_size=0 test_accumulate_call_indirect_nonstrict.php
time -v php -dopcache.jit_buffer_size=0 test_accumulate_call_indirect_strict.php
time -v php -dopcache.jit_buffer_size=0 test_accumulate_call_method_nonstrict.php
time -v php -dopcache.jit_buffer_size=0 test_accumulate_call_method_strict.php
time -v php -dopcache.jit_buffer_size=0 test_accumulate_call_closure_nonstrict.php
time -v php -dopcache.jit_buffer_size=0 test_accumulate_call_closure_strict.php
