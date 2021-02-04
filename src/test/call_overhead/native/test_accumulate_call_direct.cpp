/**
 * g++ -O3 -march=native -mavx test_accumulate_call_direct.cpp -o native_accumulate_call_direct_opt
 * g++ -O0 -march=native test_accumulate_call_direct.cpp -o native_accumulate_call_direct_no_opt
 */

#include <cstdio>

double scale(const long i) {
    return 0.001 * i;
}

double accumulate(const long iMax) {
    double fAcum = 0.0;
    for (long i = 1; i<iMax; i++) {
        fAcum += scale(i);
    }
    return fAcum;
}


int main(void) {
    double fAcum = accumulate(2000000000);
    std::printf("%g\n", fAcum);
    return 0;
}
