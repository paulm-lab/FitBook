import { ComponentFixture, TestBed } from '@angular/core/testing';

import { Seances } from './seances';
import { provideRouter } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';

describe('Seances', () => {
  let component: Seances;
  let fixture: ComponentFixture<Seances>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Seances],
      providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()],
    }).compileComponents();

    fixture = TestBed.createComponent(Seances);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
