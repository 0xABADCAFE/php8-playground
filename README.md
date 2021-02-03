# php8-playground

## About

A place for messing about with PHP8 to better understand it's theoretical and actual performance.

## PHP Version
```
PHP 8.0.1 (cli) (built: Jan 13 2021 08:21:37) ( NTS )
Copyright (c) The PHP Group
Zend Engine v4.0.1, Copyright (c) Zend Technologies
    with Zend OPcache v8.0.1, Copyright (c), by Zend Technologies

```

## JIT Settings

CLI Opcache enabled in default configuration. JIT specific parameters passed as CLI parameters to PHP for test flexibility. Unless otherwise stated, all tests conducted with the following settings:

```
opcache.jit_buffer_size=1M
opcache.jit=1255
```

## Test Hardware

All tests conducted in a single threaded model.

```
processor       : 0
vendor_id       : GenuineIntel
cpu family      : 6
model           : 142
model name      : Intel(R) Core(TM) i7-7500U CPU @ 2.70GHz
stepping        : 9
microcode       : 0xde
cpu MHz         : 2194.106
cache size      : 4096 KB
physical id     : 0
siblings        : 4
core id         : 0
cpu cores       : 2
apicid          : 0
initial apicid  : 0
fpu             : yes
fpu_exception   : yes
cpuid level     : 22
wp              : yes
flags           : fpu vme de pse tsc msr pae mce cx8 apic sep mtrr pge mca cmov pat pse36 clflush dts acpi mmx fxsr sse sse2 ss ht tm pbe syscall nx pdpe1gb rdtscp lm constant_tsc art arch_perfmon pebs bts rep_good nopl xtopology nonstop_tsc cpuid aperfmperf pni pclmulqdq dtes64 monitor ds_cpl vmx est tm2 ssse3 sdbg fma cx16 xtpr pdcm pcid sse4_1 sse4_2 x2apic movbe popcnt tsc_deadline_timer aes xsave avx f16c rdrand lahf_lm abm 3dnowprefetch cpuid_fault epb invpcid_single pti ssbd ibrs ibpb stibp tpr_shadow vnmi flexpriority ept vpid ept_ad fsgsbase tsc_adjust bmi1 avx2 smep bmi2 erms invpcid mpx rdseed adx smap clflushopt intel_pt xsaveopt xsavec xgetbv1 xsaves dtherm ida arat pln pts hwp hwp_notify hwp_act_window hwp_epp md_clear flush_l1d
bugs            : cpu_meltdown spectre_v1 spectre_v2 spec_store_bypass l1tf mds swapgs itlb_multihit srbds
bogomips        : 5799.77
clflush size    : 64
cache_alignment : 64
address sizes   : 39 bits physical, 48 bits virtual
power management:
```

## Function Call Overhead Test

Function call overhead for a small (simple multiply-accumulate) function is tested against the same operation performed inline.

- All times are User time, in seconds, collected by the `time` command.
- Strict indicates whether the code was excuted with `declare(strict_types=1)` or not.
- The Increase with JIT is the speedup factor with JIT enabled, i.e. how many times faster the code is with JIT enabled versus disabled.
- Call Overhead is the slowdown factor for calling the function relative to the inline version, i.e. how many times slower the code is versus the inline operation.

| Operation | Strict? | JIT Disabled | JIT Enabled | Increase with JIT | Call Overhead | Call Overhead (JIT) |
| --------- | ------- | ------------ | ----------- | - | - | - |
| inline    | No      | 11.87        | 2.94        | 4.04 | _N/A_ | _N/A_ |
| inline    | Yes     | 11.80        | 2.90        | 4.07 | _N/A_ | _N/A_ |
| direct    | No      | 34.26        | 12.91       | 2.65 | 2.89 | 4.39 |
| direct    | Yes     | 34.28        | 12.82       | 2.67 | 2.91 | 4.42 |
| indirect  | No      | 42.09        | 13.91       | 3.03 | 3.55 | 4.73 |
| indirect  | Yes     | 42.19        | 13.88       | 3.04 | 3.58 | 4.79 |
| method    | No      | 46.73        | 15.96       | 2.93 | 3.94 | 5.43 |
| method    | Yes     | 46.89        | 15.99       | 2.93 | 3.97 | 5.51 |
| closure   | No      | 62.39        | 16.74       | 3.73 | 5.26 | 5.69 |
| closure   | Yes     | 62.32        | 16.73       | 3.73 | 5.28 | 5.77 |

Notes:

- direct is a call do a globally defined function.
- indirect is a call by function name to a globally defined function.
- method is a call to a member function on an instance of a class.
- closure is a call to a locally declared anonymous function.

### Conclusion

- Strict type enforcement has no significant impact on either execution model.
- JIT mode can offer significant speed up for simple imperative code.
- Function call overhead remains large in either execution model but has a larger impact on JIT executed code.

### JIT Analysis

The PHP code tested in the inline case was:

```php
function accumulate(int $iMax) : float {
    $fAcum = 0.0;
    for ($i = 1; $i<$iMax; $i++) {
        $fAcum += 0.001 * $i;
    }
    return $fAcum;
}
```

The loop was been intentionally designed to make unrolling difficult to make the generated code easier to understand. We expect to see:

- Sensible register allocation for temporaries.
- A basic loop test and exit.
- Counter increment.
- Counter conversion to double.
- Multiply and accumulation.
- Sundry interrupt handling.

The generated assembler for the inline test is shown below:

```asm
TRACE-1$accumulate$13: ; (unknown)
    mov $EG(jit_trace_num), %rax
    mov $0x1, (%rax)

    // %r14 contains call frame data
    mov    0x50(%r14), %rcx          // %rcx contains $iMax
    vmovsd 0x60(%r14), %xmm2         // %xmm2 contains double accumulator $fAcum
    mov    0x70(%r14), %rdx          // %rdx contains $i
	
.L1:                                 // Loop: 11 instructions, 3 memory accesses
    cmp %rcx, %rdx                   // Compare $iMax and $i...
    jge jit$$trace_exit_0            //    Exit if $i >= $iMax
	
    vxorps    %xmm0, %xmm0, %xmm0    // XOR hack to zero out %xmm0, use as $fTemp
    vcvtsi2sd %rdx,  %xmm0, %xmm0    // Cast $i to double precision in $fTemp

    mov $0x7f4738e9f828, %rax        // Loads resolved address of literal 0.001 into %rax
    vmulsd (%rax), %xmm0, %xmm0      // $fTemp = 0.001 * $fTemp
    vaddsd %xmm0, %xmm2, %xmm2       // $fAcum += $fTemp
	
    add $0x1, %rdx                   // $i++
    mov $EG(vm_interrupt), %rax      // Test for user break or other interrupt conditions
    cmp $0x0, (%rax)                 // Interrupt data at (%rax) ?
    jz .L1                           //    If not, iterate
    jmp jit$$trace_exit_1            //    Otherwise, exit with interrupted case
```

Notes:

- The literal 0.001 was stored at address 0x7f4738e9f828 during this run.
- The literal was not assigned to a temporary for use within the loop.
- Each iteration reloaded the address into %rax and forced the use of an indirect addressing mode operand in the subsequent multiply.
- Uncessesary clobbering of registers

An improved output could be:

```asm
TRACE-1$accumulate$13: ; (unknown)
    mov    $EG(jit_trace_num), %rax
    mov    $0x1, (%rax)
    mov    0x50(%r14), %rcx
    vmovsd 0x60(%r14), %xmm2
    mov    0x70(%r14), %rdx
    
    // Loop invariants
    mov    $0x7f4738e9f828, %rax     // Resolved address of literal 0.001
    vmovsd (%rax), %xmm1             // %xmm1 contains 0.001
    mov    $EG(vm_interrupt), %rax   // %rax not clobbered past here
    
.L1:                                 // Loop: 9 instuctions, 1 memory access
    cmp %rcx, %rdx
    jge jit$$trace_exit_0
    vxorps    %xmm0, %xmm0, %xmm0    // All register to register here
    vcvtsi2sd %rdx,  %xmm0, %xmm0
    vmulsd    %xmm1, %xmm0, %xmm0
    vaddsd    %xmm0, %xmm2, %xmm2
    add       $0x1, %rdx
    cmp       $0x0, (%rax)
    jz        .L1
    jmp       jit$$trace_exit_1
```
